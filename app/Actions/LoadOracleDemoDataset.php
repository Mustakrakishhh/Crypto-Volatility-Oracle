<?php

namespace App\Actions;

use App\Enums\SyncRunStatus;
use App\Models\Asset;
use App\Models\DataSource;
use App\Models\MarketCandle;
use App\Models\NewsFeatureSnapshot;
use App\Models\SourceRecord;
use App\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class LoadOracleDemoDataset
{
    public function __construct(
        private readonly BuildVolatilityBaseline $buildVolatilityBaseline,
        private readonly BuildNewsAdjustedForecasts $buildNewsAdjustedForecasts,
    ) {}

    /**
     * @param  array<string, mixed>  $dataset
     * @return array{
     *     source_records: int,
     *     market_candles: int,
     *     news_features: int,
     *     baseline_forecasts: int,
     *     news_forecasts: int
     * }
     */
    public function handle(array $dataset, string $datasetChecksum): array
    {
        if (Arr::get($dataset, 'meta.schema_version') !== 1) {
            throw new UnexpectedValueException('Unsupported demo dataset schema version.');
        }

        $sourceRecords = $this->rows($dataset, 'source_records');
        $marketCandles = $this->rows($dataset, 'market_candles');
        $newsFeatures = $this->rows($dataset, 'news_feature_snapshots');
        $assetSymbols = collect($this->rows($dataset, 'assets'))
            ->map(fn (array $row): string => $this->requiredString($row, 'symbol'))
            ->unique()
            ->values();
        $sourceKeys = collect($this->rows($dataset, 'sources'))
            ->map(fn (array $row): string => $this->requiredString($row, 'key'))
            ->unique()
            ->values();
        $assets = Asset::query()->whereIn('symbol', $assetSymbols)->get()->keyBy('symbol');
        $sources = DataSource::query()->whereIn('key', $sourceKeys)->get()->keyBy('key');

        if ($assets->count() !== $assetSymbols->count()
            || $sources->count() !== $sourceKeys->count()) {
            throw new UnexpectedValueException(
                'Demo reference data is missing. Run php artisan migrate --seed first.',
            );
        }

        DB::transaction(function () use (
            $dataset,
            $datasetChecksum,
            $sourceRecords,
            $marketCandles,
            $newsFeatures,
            $assets,
            $sources,
        ): void {
            $syncRuns = [];

            foreach ($sources as $sourceKey => $source) {
                $rawCount = collect($sourceRecords)->filter(
                    fn (array $row): bool => $this->requiredString($row, 'source_key') === $sourceKey,
                )->count();
                $storedCount = collect($marketCandles)->filter(
                    fn (array $row): bool => $this->requiredString($row, 'source_key') === $sourceKey,
                )->count() + collect($newsFeatures)->filter(
                    fn (array $row): bool => $this->requiredString($row, 'source_key') === $sourceKey,
                )->count();
                $syncRun = $source->syncRuns()
                    ->where('context->dataset_checksum', $datasetChecksum)
                    ->first();

                if (! $syncRun instanceof SyncRun) {
                    $syncRun = $source->syncRuns()->make();
                }

                $syncRun->fill([
                    'status' => SyncRunStatus::Completed,
                    'started_at' => now('UTC'),
                    'finished_at' => now('UTC'),
                    'records_received' => $rawCount,
                    'records_stored' => $storedCount,
                    'error_message' => null,
                    'context' => [
                        'mode' => 'demo_dataset',
                        'dataset_checksum' => $datasetChecksum,
                        'dataset_name' => Arr::get($dataset, 'meta.dataset_name'),
                        'range_start' => Arr::get($dataset, 'meta.range_start'),
                        'range_end' => Arr::get($dataset, 'meta.range_end'),
                    ],
                ]);
                $syncRun->save();
                $syncRuns[$sourceKey] = $syncRun;
            }

            foreach (array_chunk($marketCandles, 500) as $chunk) {
                $timestamp = now('UTC');
                $rows = array_map(fn (array $row): array => [
                    'asset_id' => $this->assetId($assets, $row),
                    'data_source_id' => $this->sourceId($sources, $row),
                    'interval_minutes' => $this->requiredInt($row, 'interval_minutes'),
                    'opened_at' => CarbonImmutable::parse($this->requiredString($row, 'opened_at'), 'UTC'),
                    'closed_at' => CarbonImmutable::parse($this->requiredString($row, 'closed_at'), 'UTC'),
                    'open' => $this->requiredString($row, 'open'),
                    'high' => $this->requiredString($row, 'high'),
                    'low' => $this->requiredString($row, 'low'),
                    'close' => $this->requiredString($row, 'close'),
                    'volume' => $this->requiredString($row, 'volume'),
                    'turnover' => $this->requiredString($row, 'turnover'),
                    'is_closed' => $this->requiredBool($row, 'is_closed'),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ], $chunk);
                MarketCandle::query()->upsert(
                    $rows,
                    ['asset_id', 'data_source_id', 'interval_minutes', 'opened_at'],
                    ['closed_at', 'open', 'high', 'low', 'close', 'volume', 'turnover', 'is_closed', 'updated_at'],
                );
            }

            foreach (array_chunk($newsFeatures, 500) as $chunk) {
                $timestamp = now('UTC');
                $rows = array_map(fn (array $row): array => [
                    'asset_id' => $this->assetId($assets, $row),
                    'data_source_id' => $this->sourceId($sources, $row),
                    'observed_on' => $this->requiredString($row, 'observed_on'),
                    'article_count' => $this->requiredInt($row, 'article_count'),
                    'monitored_article_count' => $this->nullableInt($row, 'monitored_article_count'),
                    'coverage_share_pct' => $this->nullableString($row, 'coverage_share_pct'),
                    'average_tone' => $this->nullableString($row, 'average_tone'),
                    'query' => $this->requiredString($row, 'query'),
                    'provider_version' => $this->requiredString($row, 'provider_version'),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ], $chunk);
                NewsFeatureSnapshot::query()->upsert(
                    $rows,
                    ['asset_id', 'data_source_id', 'observed_on'],
                    [
                        'article_count',
                        'monitored_article_count',
                        'coverage_share_pct',
                        'average_tone',
                        'query',
                        'provider_version',
                        'updated_at',
                    ],
                );
            }

            foreach (array_chunk($sourceRecords, 500) as $chunk) {
                $timestamp = now('UTC');
                $rows = array_map(function (array $row) use ($assets, $sources, $syncRuns, $timestamp): array {
                    $sourceKey = $this->requiredString($row, 'source_key');
                    $assetSymbol = $this->nullableString($row, 'asset_symbol');
                    $payload = $this->requiredArray($row, 'payload');
                    $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

                    return [
                        'data_source_id' => $this->sourceId($sources, $row),
                        'asset_id' => $assetSymbol === null ? null : $this->assetId($assets, $row),
                        'sync_run_id' => $syncRuns[$sourceKey]->id,
                        'record_type' => $this->requiredString($row, 'record_type'),
                        'provider_key' => $this->requiredString($row, 'provider_key'),
                        'observed_at' => CarbonImmutable::parse(
                            $this->requiredString($row, 'observed_at'),
                            'UTC',
                        ),
                        'payload' => $encodedPayload,
                        'checksum' => hash('sha256', $encodedPayload),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }, $chunk);
                SourceRecord::query()->upsert(
                    $rows,
                    ['data_source_id', 'record_type', 'provider_key'],
                    ['asset_id', 'sync_run_id', 'observed_at', 'payload', 'checksum', 'updated_at'],
                );
            }
        });

        $symbols = array_values($assetSymbols->all());
        $baseline = $this->buildVolatilityBaseline->handle($symbols);
        $newsModel = $this->buildNewsAdjustedForecasts->handle($symbols);

        return [
            'source_records' => count($sourceRecords),
            'market_candles' => count($marketCandles),
            'news_features' => count($newsFeatures),
            'baseline_forecasts' => $baseline['forecasts'],
            'news_forecasts' => $newsModel['forecasts'],
        ];
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return list<array<string, mixed>>
     */
    private function rows(array $dataset, string $key): array
    {
        $rows = $dataset[$key] ?? null;

        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new UnexpectedValueException("Demo dataset section {$key} must be a list.");
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new UnexpectedValueException("Demo dataset section {$key} contains an invalid row.");
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    private function requiredString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new UnexpectedValueException("Demo dataset field {$key} must be a non-empty string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new UnexpectedValueException("Demo dataset field {$key} must be a string or null.");
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function requiredInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (! is_int($value)) {
            throw new UnexpectedValueException("Demo dataset field {$key} must be an integer.");
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function nullableInt(array $row, string $key): ?int
    {
        $value = $row[$key] ?? null;

        if ($value !== null && ! is_int($value)) {
            throw new UnexpectedValueException("Demo dataset field {$key} must be an integer or null.");
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function requiredBool(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;

        if (! is_bool($value)) {
            throw new UnexpectedValueException("Demo dataset field {$key} must be a boolean.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function requiredArray(array $row, string $key): array
    {
        $value = $row[$key] ?? null;

        if (! is_array($value)) {
            throw new UnexpectedValueException("Demo dataset field {$key} must be an object.");
        }

        return $value;
    }

    /**
     * @param  Collection<string, Asset>  $assets
     * @param  array<string, mixed>  $row
     */
    private function assetId($assets, array $row): int
    {
        $asset = $assets->get($this->requiredString($row, 'asset_symbol'));

        if (! $asset instanceof Asset) {
            throw new UnexpectedValueException('Demo dataset references an unknown asset.');
        }

        return $asset->id;
    }

    /**
     * @param  Collection<string, DataSource>  $sources
     * @param  array<string, mixed>  $row
     */
    private function sourceId($sources, array $row): int
    {
        $source = $sources->get($this->requiredString($row, 'source_key'));

        if (! $source instanceof DataSource) {
            throw new UnexpectedValueException('Demo dataset references an unknown source.');
        }

        return $source->id;
    }
}
