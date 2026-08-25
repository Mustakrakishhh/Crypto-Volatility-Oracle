<?php

namespace App\Actions;

use App\Models\Asset;
use App\Models\NewsFeatureSnapshot;
use App\Models\VolatilityForecast;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @phpstan-type NewsInputs array{
 *     source: string,
 *     feature_date: string,
 *     lag_days: int,
 *     article_count: int,
 *     attention_metric: string,
 *     attention_value: float,
 *     trailing_attention_value: float,
 *     coverage_share_pct: float|null,
 *     trailing_coverage_share_pct: float|null,
 *     attention_ratio: float,
 *     average_tone: float,
 *     multiplier: float
 * }
 */
class BuildNewsAdjustedForecasts
{
    public const MODEL_VERSION = 'news-adjusted-rv-v1';

    /**
     * @param  list<string>  $symbols
     * @return array{assets: int, forecasts: int, evaluated: int, skipped: int}
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
            throw new InvalidArgumentException('No active assets matched the news-adjusted forecast request.');
        }

        $summary = ['assets' => 0, 'forecasts' => 0, 'evaluated' => 0, 'skipped' => 0];

        foreach ($assets as $asset) {
            $assetSummary = $this->buildForAsset($asset);
            $summary['assets']++;
            $summary['forecasts'] += $assetSummary['forecasts'];
            $summary['evaluated'] += $assetSummary['evaluated'];
            $summary['skipped'] += $assetSummary['skipped'];
        }

        return $summary;
    }

    /** @return array{forecasts: int, evaluated: int, skipped: int} */
    private function buildForAsset(Asset $asset): array
    {
        $baselineForecasts = $asset->volatilityForecasts()
            ->where('model_version', BuildVolatilityBaseline::MODEL_VERSION)
            ->oldest('predicted_at')
            ->get();
        $newsBySource = $this->newsBySource($asset);
        $summary = ['forecasts' => 0, 'evaluated' => 0, 'skipped' => 0];

        foreach ($baselineForecasts as $baselineForecast) {
            $inputs = $this->newsInputs($baselineForecast->predicted_at, $newsBySource);

            if ($inputs === null) {
                $summary['skipped']++;

                continue;
            }

            $predictedVolatility = (float) $baselineForecast->predicted_volatility_pct * $inputs['multiplier'];
            $actualVolatility = $baselineForecast->actual_volatility_pct === null
                ? null
                : (float) $baselineForecast->actual_volatility_pct;
            $absoluteError = $actualVolatility === null
                ? null
                : abs($predictedVolatility - $actualVolatility);
            $forecast = VolatilityForecast::query()->updateOrCreate(
                [
                    'feature_snapshot_id' => $baselineForecast->feature_snapshot_id,
                    'model_version' => self::MODEL_VERSION,
                ],
                [
                    'asset_id' => $asset->id,
                    'predicted_at' => $baselineForecast->predicted_at,
                    'target_at' => $baselineForecast->target_at,
                    'horizon_hours' => $baselineForecast->horizon_hours,
                    'predicted_volatility_pct' => $predictedVolatility,
                    'status' => $baselineForecast->status,
                    'actual_volatility_pct' => $actualVolatility,
                    'absolute_error' => $absoluteError,
                    'squared_error' => $absoluteError === null ? null : $absoluteError ** 2,
                    'evaluated_at' => $baselineForecast->evaluated_at,
                    'model_inputs' => $inputs,
                ],
            );
            $summary['forecasts']++;

            if ($forecast->status === 'evaluated') {
                $summary['evaluated']++;
            }
        }

        $this->calibrateLatestForecast($asset);

        return $summary;
    }

    /**
     * @param  array<string, Collection<string, NewsFeatureSnapshot>>  $newsBySource
     * @return NewsInputs|null
     */
    private function newsInputs(CarbonInterface $predictedAt, array $newsBySource): ?array
    {
        $featureDate = $predictedAt->copy()->utc()->startOfDay()->subDay();

        foreach (['gdelt', 'alpha-vantage'] as $sourceKey) {
            $newsByDate = $newsBySource[$sourceKey] ?? null;

            if (! $newsByDate instanceof Collection) {
                continue;
            }

            $inputs = $this->newsInputsForSource($featureDate, $sourceKey, $newsByDate);

            if ($inputs !== null) {
                return $inputs;
            }
        }

        return null;
    }

    /**
     * @param  Collection<string, NewsFeatureSnapshot>  $newsByDate
     * @return NewsInputs|null
     */
    private function newsInputsForSource(
        CarbonInterface $featureDate,
        string $sourceKey,
        Collection $newsByDate,
    ): ?array {
        $current = $newsByDate->get($featureDate->toDateString());
        $attention = $current instanceof NewsFeatureSnapshot
            ? $this->attentionValue($current, $sourceKey)
            : null;

        if (! $current instanceof NewsFeatureSnapshot || $attention === null) {
            return null;
        }

        $trailingAttention = collect();

        for ($daysAgo = 1; $daysAgo <= 7; $daysAgo++) {
            $snapshot = $newsByDate->get($featureDate->copy()->subDays($daysAgo)->toDateString());
            $value = $snapshot instanceof NewsFeatureSnapshot
                ? $this->attentionValue($snapshot, $sourceKey)
                : null;

            if ($value !== null) {
                $trailingAttention->push($value);
            }
        }

        if ($trailingAttention->count() < 3) {
            return null;
        }

        $trailingAverage = (float) $trailingAttention->average();
        $attentionRatio = $trailingAverage <= 0 ? 1.0 : $attention / $trailingAverage;
        $attentionShock = min(2.0, max(0.0, $attentionRatio - 1));
        $tone = $current->average_tone === null ? 0.0 : (float) $current->average_tone;
        $negativeToneShock = min(1.0, max(0.0, $sourceKey === 'alpha-vantage' ? -$tone : -$tone / 10));
        $multiplier = min(1.4, 1 + (0.15 * $attentionShock) + (0.10 * $negativeToneShock));
        $usesCoverage = $sourceKey === 'gdelt';

        return [
            'source' => $current->provider_version,
            'feature_date' => $featureDate->toDateString(),
            'lag_days' => 1,
            'article_count' => $current->article_count,
            'attention_metric' => $usesCoverage ? 'coverage_share_pct' : 'article_count',
            'attention_value' => round($attention, 8),
            'trailing_attention_value' => round($trailingAverage, 8),
            'coverage_share_pct' => $usesCoverage ? round($attention, 8) : null,
            'trailing_coverage_share_pct' => $usesCoverage ? round($trailingAverage, 8) : null,
            'attention_ratio' => round($attentionRatio, 4),
            'average_tone' => round($tone, 4),
            'multiplier' => round($multiplier, 4),
        ];
    }

    private function attentionValue(NewsFeatureSnapshot $snapshot, string $sourceKey): ?float
    {
        if ($sourceKey === 'alpha-vantage') {
            return (float) $snapshot->article_count;
        }

        return $snapshot->coverage_share_pct === null
            ? null
            : (float) $snapshot->coverage_share_pct;
    }

    /** @return array<string, Collection<string, NewsFeatureSnapshot>> */
    private function newsBySource(Asset $asset): array
    {
        $snapshots = $asset->newsFeatureSnapshots()
            ->select([
                'id',
                'asset_id',
                'data_source_id',
                'observed_on',
                'article_count',
                'coverage_share_pct',
                'average_tone',
                'provider_version',
            ])
            ->with('dataSource:id,key')
            ->oldest('observed_on')
            ->get();
        $newsBySource = [];

        foreach ($snapshots as $snapshot) {
            $sourceKey = $snapshot->dataSource->key;
            $newsBySource[$sourceKey] ??= collect();
            $newsBySource[$sourceKey]->put($snapshot->observed_on->toDateString(), $snapshot);
        }

        return $newsBySource;
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
