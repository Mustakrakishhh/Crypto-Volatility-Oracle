<?php

namespace App\Actions;

use App\Models\Asset;
use App\Models\DataSource;
use App\Models\FeatureSnapshot;
use App\Models\MarketCandle;
use App\Models\VolatilityForecast;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BuildVolatilityBaseline
{
    public const FEATURE_VERSION = 'market-features-v1';

    public const MODEL_VERSION = 'persistence-rv-v1';

    public function __construct(private readonly CalculateMarketMetrics $calculateMarketMetrics) {}

    /**
     * @param  list<string>  $symbols
     * @return array{assets: int, snapshots: int, forecasts: int, evaluated: int}
     */
    public function handle(array $symbols = []): array
    {
        $normalizedSymbols = array_values(array_unique(array_map('strtoupper', $symbols)));
        $assets = Asset::query()
            ->where('is_active', true)
            ->when($normalizedSymbols !== [], fn ($query) => $query->whereIn('symbol', $normalizedSymbols))
            ->orderBy('display_order')
            ->get();

        if ($assets->isEmpty()) {
            throw new InvalidArgumentException('No active assets matched the baseline request.');
        }

        $dataSource = DataSource::query()->where('key', 'bybit')->firstOrFail();
        $summary = ['assets' => 0, 'snapshots' => 0, 'forecasts' => 0, 'evaluated' => 0];

        foreach ($assets as $asset) {
            $candles = MarketCandle::query()
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
                ->whereBelongsTo($asset)
                ->whereBelongsTo($dataSource)
                ->where('interval_minutes', 60)
                ->where('is_closed', true)
                ->oldest('opened_at')
                ->get();

            $assetSummary = $this->buildForAsset($asset, $dataSource, $candles);
            $summary['assets']++;
            $summary['snapshots'] += $assetSummary['snapshots'];
            $summary['forecasts'] += $assetSummary['forecasts'];
            $summary['evaluated'] += $assetSummary['evaluated'];
        }

        return $summary;
    }

    /**
     * @param  Collection<int, MarketCandle>  $candles
     * @return array{snapshots: int, forecasts: int, evaluated: int}
     */
    private function buildForAsset(Asset $asset, DataSource $dataSource, Collection $candles): array
    {
        if ($candles->count() < 25) {
            return ['snapshots' => 0, 'forecasts' => 0, 'evaluated' => 0];
        }

        $sampleIndexes = [];

        for ($index = 24; $index < $candles->count(); $index += 24) {
            $sampleIndexes[] = $index;
        }

        $latestIndex = $candles->count() - 1;

        if (! in_array($latestIndex, $sampleIndexes, true)) {
            $sampleIndexes[] = $latestIndex;
        }

        $summary = ['snapshots' => 0, 'forecasts' => 0, 'evaluated' => 0];

        DB::transaction(function () use ($asset, $dataSource, $candles, $sampleIndexes, &$summary): void {
            foreach ($sampleIndexes as $index) {
                $pastWindow = new Collection($candles->slice($index - 24, 25)->values()->all());
                $pastMetrics = $this->calculateMarketMetrics->handle($pastWindow);

                if ($pastMetrics['volatility_24h'] === null
                    || $pastMetrics['change_24h'] === null
                    || $pastMetrics['risk_score'] === null
                    || $pastMetrics['risk_level'] === null) {
                    continue;
                }

                $currentCandle = $candles->get($index);

                if (! $currentCandle instanceof MarketCandle) {
                    continue;
                }

                $snapshot = FeatureSnapshot::query()->updateOrCreate(
                    [
                        'asset_id' => $asset->id,
                        'calculated_at' => $currentCandle->closed_at,
                        'horizon_hours' => 24,
                        'algorithm_version' => self::FEATURE_VERSION,
                    ],
                    [
                        'data_source_id' => $dataSource->id,
                        'price' => $currentCandle->close,
                        'change_pct' => $pastMetrics['change_24h'],
                        'realized_volatility_pct' => $pastMetrics['volatility_24h'],
                        'risk_score' => $pastMetrics['risk_score'],
                        'risk_level' => $pastMetrics['risk_level'],
                        'data_completeness' => $pastMetrics['data_completeness'],
                        'features' => [
                            'observed_returns' => $pastMetrics['observed_returns'],
                            'expected_returns' => $pastMetrics['expected_returns'],
                            'factors' => $pastMetrics['factors'],
                        ],
                    ],
                );
                $summary['snapshots']++;

                $actualVolatility = null;

                if ($index + 24 < $candles->count()) {
                    $futureWindow = new Collection($candles->slice($index, 25)->values()->all());
                    $futureMetrics = $this->calculateMarketMetrics->handle($futureWindow);
                    $actualVolatility = $futureMetrics['volatility_24h'];
                }

                $predictedVolatility = $pastMetrics['volatility_24h'];
                $absoluteError = $actualVolatility === null
                    ? null
                    : abs($predictedVolatility - $actualVolatility);
                $forecast = VolatilityForecast::query()->updateOrCreate(
                    [
                        'feature_snapshot_id' => $snapshot->id,
                        'model_version' => self::MODEL_VERSION,
                    ],
                    [
                        'asset_id' => $asset->id,
                        'predicted_at' => $currentCandle->closed_at,
                        'target_at' => $currentCandle->closed_at->copy()->addHours(24),
                        'horizon_hours' => 24,
                        'predicted_volatility_pct' => $predictedVolatility,
                        'status' => $actualVolatility === null ? 'pending' : 'evaluated',
                        'actual_volatility_pct' => $actualVolatility,
                        'absolute_error' => $absoluteError,
                        'squared_error' => $absoluteError === null ? null : $absoluteError ** 2,
                        'model_inputs' => [
                            'source' => 'bybit',
                            'method' => 'persistence',
                            'observed_window_end' => $currentCandle->closed_at->toIso8601String(),
                            'realized_volatility_pct' => round($predictedVolatility, 6),
                            'change_pct' => round($pastMetrics['change_24h'], 6),
                            'data_completeness' => $pastMetrics['data_completeness'],
                        ],
                        'evaluated_at' => $actualVolatility === null
                            ? null
                            : $currentCandle->closed_at->copy()->addHours(24),
                    ],
                );
                $summary['forecasts']++;

                if ($forecast->status === 'evaluated') {
                    $summary['evaluated']++;
                }
            }
        });

        $this->calibrateLatestForecast($asset);

        return $summary;
    }

    private function calibrateLatestForecast(Asset $asset): void
    {
        $evaluatedQuery = $asset->volatilityForecasts()
            ->where('model_version', self::MODEL_VERSION)
            ->where('status', 'evaluated');
        $sampleSize = (clone $evaluatedQuery)->count();

        if ($sampleSize === 0) {
            return;
        }

        $mae = (float) (clone $evaluatedQuery)->avg('absolute_error');
        $latestForecast = $asset->volatilityForecasts()
            ->where('model_version', self::MODEL_VERSION)
            ->latest('predicted_at')
            ->first();

        if (! $latestForecast instanceof VolatilityForecast) {
            return;
        }

        $predictedVolatility = (float) $latestForecast->predicted_volatility_pct;
        $confidence = max(10, min(95, 100 - (($mae / max($predictedVolatility, 0.5)) * 100)));
        $latestForecast->update([
            'lower_bound_pct' => max(0, $predictedVolatility - $mae),
            'upper_bound_pct' => $predictedVolatility + $mae,
            'confidence_pct' => round($confidence, 2),
        ]);
    }
}
