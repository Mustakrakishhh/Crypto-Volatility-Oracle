<?php

namespace App\Actions;

use App\Enums\SyncRunStatus;
use App\Models\Asset;
use App\Models\DataSource;
use App\Models\SyncRun;
use App\Models\VolatilityForecast;
use Illuminate\Support\Carbon;

class GetOracleDashboardData
{
    public function __construct(private readonly CalculateMarketMetrics $calculateMarketMetrics) {}

    /**
     * @return array{
     *     assets: list<array<string, mixed>>,
     *     sources: list<array<string, mixed>>,
     *     overview: array<string, int|string|null>
     * }
     */
    public function handle(): array
    {
        $assets = Asset::query()
            ->select(['id', 'symbol', 'name', 'base_currency', 'quote_currency', 'display_order'])
            ->where('is_active', true)
            ->with([
                'latestMarketCandle',
                'marketCandles' => fn ($query) => $query
                    ->select([
                        'id',
                        'asset_id',
                        'data_source_id',
                        'interval_minutes',
                        'opened_at',
                        'closed_at',
                        'close',
                        'is_closed',
                    ])
                    ->where('is_closed', true)
                    ->where('interval_minutes', 60)
                    ->latest('opened_at')
                    ->limit(25),
                'volatilityForecasts' => fn ($query) => $query
                    ->select([
                        'id',
                        'asset_id',
                        'predicted_at',
                        'target_at',
                        'horizon_hours',
                        'predicted_volatility_pct',
                        'lower_bound_pct',
                        'upper_bound_pct',
                        'confidence_pct',
                        'status',
                        'model_version',
                        'model_inputs',
                    ])
                    ->where('model_version', BuildVolatilityBaseline::MODEL_VERSION)
                    ->latest('predicted_at')
                    ->limit(1),
                'sourceMappings' => fn ($query) => $query
                    ->select(['id', 'asset_id', 'data_source_id', 'external_identifier'])
                    ->where('is_active', true)
                    ->with('dataSource:id,key,name,type,is_active'),
            ])
            ->orderBy('display_order')
            ->get();

        $sources = DataSource::query()
            ->select(['id', 'key', 'name', 'type', 'base_url', 'is_active'])
            ->with(['latestSyncRun', 'latestCompletedSyncRun'])
            ->orderBy('id')
            ->get();

        $backtestsByAsset = VolatilityForecast::query()
            ->select(['asset_id', 'model_version'])
            ->selectRaw('COUNT(*) as sample_size')
            ->selectRaw('AVG(absolute_error) as mean_absolute_error')
            ->selectRaw('AVG(squared_error) as mean_squared_error')
            ->whereIn('asset_id', $assets->pluck('id'))
            ->whereIn('model_version', [
                BuildVolatilityBaseline::MODEL_VERSION,
                BuildNewsAdjustedForecasts::MODEL_VERSION,
            ])
            ->where('status', 'evaluated')
            ->groupBy(['asset_id', 'model_version'])
            ->get()
            ->keyBy(fn (VolatilityForecast $forecast): string => "{$forecast->asset_id}:{$forecast->model_version}");

        $latestNewsForecastsByAsset = VolatilityForecast::query()
            ->select([
                'id',
                'asset_id',
                'predicted_at',
                'target_at',
                'predicted_volatility_pct',
                'lower_bound_pct',
                'upper_bound_pct',
                'confidence_pct',
                'model_version',
                'model_inputs',
            ])
            ->whereIn('asset_id', $assets->pluck('id'))
            ->where('model_version', BuildNewsAdjustedForecasts::MODEL_VERSION)
            ->latest('predicted_at')
            ->get()
            ->unique('asset_id')
            ->keyBy('asset_id');

        $latestSyncAt = SyncRun::query()
            ->where('status', SyncRunStatus::Completed)
            ->whereNotNull('finished_at')
            ->max('finished_at');

        $mappedAssets = [];

        foreach ($assets as $asset) {
            $mappedAssets[] = $this->mapAsset(
                $asset,
                $backtestsByAsset->get("{$asset->id}:".BuildVolatilityBaseline::MODEL_VERSION),
                $latestNewsForecastsByAsset->get($asset->id),
                $backtestsByAsset->get("{$asset->id}:".BuildNewsAdjustedForecasts::MODEL_VERSION),
            );
        }

        $mappedSources = [];

        foreach ($sources as $source) {
            $mappedSources[] = $this->mapSource($source);
        }

        return [
            'assets' => $mappedAssets,
            'sources' => $mappedSources,
            'overview' => [
                'assets_count' => $assets->count(),
                'sources_count' => $sources->count(),
                'active_sources_count' => $sources->where('is_active', true)->count(),
                'forecast_horizon_hours' => 24,
                'latest_sync_at' => $latestSyncAt === null ? null : Carbon::parse($latestSyncAt)->toIso8601String(),
                'data_state' => $latestSyncAt === null ? 'waiting' : 'ready',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function mapAsset(
        Asset $asset,
        ?VolatilityForecast $baselineBacktest,
        ?VolatilityForecast $newsForecast,
        ?VolatilityForecast $newsBacktest,
    ): array {
        $latestCandle = $asset->latestMarketCandle;
        $latestForecast = $asset->volatilityForecasts->first();
        $marketMetrics = $this->calculateMarketMetrics->handle($asset->marketCandles);
        $sources = [];

        foreach ($asset->sourceMappings as $mapping) {
            if ($mapping->dataSource === null) {
                continue;
            }

            $sources[] = [
                'key' => $mapping->dataSource->key,
                'name' => $mapping->dataSource->name,
                'type' => $mapping->dataSource->type->value,
                'external_identifier' => $mapping->external_identifier,
            ];
        }

        return [
            'id' => $asset->id,
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'base_currency' => $asset->base_currency,
            'quote_currency' => $asset->quote_currency,
            'sources' => $sources,
            'market' => [
                'last_price' => $latestCandle?->close,
                'change_24h' => $marketMetrics['change_24h'],
                'volatility_24h' => $marketMetrics['volatility_24h'],
                'updated_at' => $latestCandle?->opened_at->toIso8601String(),
            ],
            'analysis' => [
                'status' => $marketMetrics['risk_score'] === null ? 'waiting_for_history' : 'ready',
                'data_completeness' => $marketMetrics['data_completeness'],
                'observed_returns' => $marketMetrics['observed_returns'],
                'expected_returns' => $marketMetrics['expected_returns'],
                'window_started_at' => $marketMetrics['window_started_at'],
                'window_ended_at' => $marketMetrics['window_ended_at'],
                'risk_score' => $marketMetrics['risk_score'],
                'risk_level' => $marketMetrics['risk_level'],
                'risk_label' => $marketMetrics['risk_label'],
                'factors' => $marketMetrics['factors'],
            ],
            'forecast' => [
                'status' => match (true) {
                    $latestCandle === null => 'waiting_for_data',
                    $latestForecast === null => 'collecting_history',
                    default => 'ready',
                },
                'label' => match (true) {
                    $latestCandle === null => 'Ожидает данных',
                    $latestForecast === null => 'Накапливает историю',
                    default => 'Persistence baseline',
                },
                'confidence' => $latestForecast?->confidence_pct === null
                    ? null
                    : (float) $latestForecast->confidence_pct,
                'risk' => $latestForecast === null
                    ? null
                    : $this->forecastRiskLevel((float) $latestForecast->predicted_volatility_pct),
                'risk_label' => $latestForecast === null
                    ? null
                    : $this->forecastRiskLabel((float) $latestForecast->predicted_volatility_pct),
                'horizon_hours' => 24,
                'predicted_volatility' => $latestForecast?->predicted_volatility_pct === null
                    ? null
                    : (float) $latestForecast->predicted_volatility_pct,
                'lower_bound' => $latestForecast?->lower_bound_pct === null
                    ? null
                    : (float) $latestForecast->lower_bound_pct,
                'upper_bound' => $latestForecast?->upper_bound_pct === null
                    ? null
                    : (float) $latestForecast->upper_bound_pct,
                'predicted_at' => $latestForecast?->predicted_at->toIso8601String(),
                'target_at' => $latestForecast?->target_at->toIso8601String(),
                'model_version' => $latestForecast?->model_version,
                'backtest' => [
                    'sample_size' => (int) data_get($baselineBacktest, 'sample_size', 0),
                    'mae' => $baselineBacktest === null
                        ? null
                        : round((float) data_get($baselineBacktest, 'mean_absolute_error'), 2),
                    'rmse' => $baselineBacktest === null
                        ? null
                        : round(sqrt((float) data_get($baselineBacktest, 'mean_squared_error')), 2),
                ],
                'arguments' => $this->forecastArguments(
                    $latestForecast,
                    $newsForecast,
                    $baselineBacktest,
                ),
                'failure_risks' => $this->forecastFailureRisks(
                    $latestForecast,
                    $baselineBacktest,
                ),
            ],
            'model_comparison' => $this->modelComparison(
                $baselineBacktest,
                $newsForecast,
                $newsBacktest,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function modelComparison(
        ?VolatilityForecast $baselineBacktest,
        ?VolatilityForecast $newsForecast,
        ?VolatilityForecast $newsBacktest,
    ): array {
        $baselineMae = $baselineBacktest === null
            ? null
            : (float) data_get($baselineBacktest, 'mean_absolute_error');
        $newsMae = $newsBacktest === null
            ? null
            : (float) data_get($newsBacktest, 'mean_absolute_error');
        $sampleSize = (int) data_get($newsBacktest, 'sample_size', 0);
        $improvement = $baselineMae === null || $newsMae === null || $baselineMae <= 0
            ? null
            : (($baselineMae - $newsMae) / $baselineMae) * 100;
        $verdict = match (true) {
            $newsForecast === null || $sampleSize < 30 || $improvement === null => 'insufficient_data',
            $improvement > 0 => 'candidate_better',
            $improvement < 0 => 'baseline_better',
            default => 'tie',
        };

        return [
            'status' => $newsForecast === null ? 'waiting_for_news' : 'ready',
            'verdict' => $verdict,
            'mae_improvement_pct' => $improvement === null ? null : round($improvement, 2),
            'candidate' => [
                'model_version' => $newsForecast?->model_version,
                'predicted_volatility' => $newsForecast?->predicted_volatility_pct === null
                    ? null
                    : (float) $newsForecast->predicted_volatility_pct,
                'confidence' => $newsForecast?->confidence_pct === null
                    ? null
                    : (float) $newsForecast->confidence_pct,
                'sample_size' => $sampleSize,
                'mae' => $newsMae === null ? null : round($newsMae, 2),
                'rmse' => $newsBacktest === null
                    ? null
                    : round(sqrt((float) data_get($newsBacktest, 'mean_squared_error')), 2),
                'news_inputs' => $newsForecast?->model_inputs,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function mapSource(DataSource $source): array
    {
        $latestAttempt = $source->latestSyncRun;

        return [
            'id' => $source->id,
            'key' => $source->key,
            'name' => $source->name,
            'type' => $source->type->value,
            'base_url' => $source->base_url,
            'is_active' => $source->is_active,
            'status' => $this->sourceStatus($latestAttempt),
            'latest_sync' => $this->mapSyncRun($source->latestCompletedSyncRun),
            'latest_attempt' => $this->mapSyncRun($latestAttempt),
        ];
    }

    /** @return array<string, int|string|null>|null */
    private function mapSyncRun(?SyncRun $syncRun): ?array
    {
        if ($syncRun === null) {
            return null;
        }

        return [
            'status' => $syncRun->status->value,
            'started_at' => $syncRun->started_at->toIso8601String(),
            'finished_at' => $syncRun->finished_at?->toIso8601String(),
            'records_received' => $syncRun->records_received,
            'records_stored' => $syncRun->records_stored,
            'error_message' => $syncRun->error_message,
        ];
    }

    private function sourceStatus(?SyncRun $latestRun): string
    {
        return match ($latestRun?->status) {
            null, SyncRunStatus::Pending => 'waiting',
            SyncRunStatus::Running => 'syncing',
            SyncRunStatus::Completed => 'online',
            SyncRunStatus::Failed => 'error',
        };
    }

    private function forecastRiskLevel(float $predictedVolatility): string
    {
        return match (true) {
            $predictedVolatility < 2 => 'low',
            $predictedVolatility < 4 => 'moderate',
            $predictedVolatility < 6 => 'high',
            default => 'extreme',
        };
    }

    private function forecastRiskLabel(float $predictedVolatility): string
    {
        return match ($this->forecastRiskLevel($predictedVolatility)) {
            'low' => 'Низкий',
            'moderate' => 'Умеренный',
            'high' => 'Высокий',
            default => 'Экстремальный',
        };
    }

    /** @return list<string> */
    private function forecastArguments(
        ?VolatilityForecast $baselineForecast,
        ?VolatilityForecast $newsForecast,
        ?VolatilityForecast $baselineBacktest,
    ): array {
        if ($baselineForecast === null) {
            return [];
        }

        $inputs = $baselineForecast->model_inputs ?? [];
        $realizedVolatility = (float) data_get(
            $inputs,
            'realized_volatility_pct',
            $baselineForecast->predicted_volatility_pct,
        );
        $arguments = [
            sprintf(
                'Persistence: измеренная RV последних 24 часов %.2f%% перенесена на следующие 24 часа.',
                $realizedVolatility,
            ),
        ];
        $newsInputs = $newsForecast?->model_inputs;

        if (is_array($newsInputs)) {
            $provider = match (data_get($newsInputs, 'source')) {
                'alpha-vantage-news-sentiment' => 'Alpha Vantage',
                default => 'GDELT',
            };
            $arguments[] = sprintf(
                '%s: %d статей, tone %.2f и attention ratio %.2f дали множитель ×%.3f.',
                $provider,
                (int) data_get($newsInputs, 'article_count', 0),
                (float) data_get($newsInputs, 'average_tone', 0),
                (float) data_get($newsInputs, 'attention_ratio', 1),
                (float) data_get($newsInputs, 'multiplier', 1),
            );
        }

        $sampleSize = (int) data_get($baselineBacktest, 'sample_size', 0);
        $mae = data_get($baselineBacktest, 'mean_absolute_error');

        if ($sampleSize > 0 && is_numeric($mae)) {
            $arguments[] = sprintf(
                'Backtest: %d завершённых окон, средняя абсолютная ошибка %.2f п.п.',
                $sampleSize,
                (float) $mae,
            );
        }

        return $arguments;
    }

    /** @return list<string> */
    private function forecastFailureRisks(
        ?VolatilityForecast $baselineForecast,
        ?VolatilityForecast $baselineBacktest,
    ): array {
        if ($baselineForecast === null) {
            return [];
        }

        $risks = [
            'Новый ценовой шок может изменить режим рынка: persistence использует прошлую RV и не предвидит событие.',
            'Новостные агрегаторы могут включать нерелевантные упоминания тикеров и не охватывают все источники.',
        ];
        $mae = data_get($baselineBacktest, 'mean_absolute_error');

        if (is_numeric($mae)) {
            $risks[] = sprintf(
                'Исторический MAE %.2f п.п. показывает типичный масштаб промаха, но не ограничивает максимальную ошибку.',
                (float) $mae,
            );
        }

        return $risks;
    }
}
