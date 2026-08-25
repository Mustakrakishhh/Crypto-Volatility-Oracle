<?php

namespace App\Actions;

use App\Enums\SyncRunStatus;
use App\Models\AssetSourceMapping;
use App\Models\DataSource;
use App\Models\NewsFeatureSnapshot;
use App\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class SyncGdeltNewsFeatures
{
    public function __construct(
        private readonly FetchGdeltTimeline $fetchGdeltTimeline,
        private readonly StoreSourceRecords $storeSourceRecords,
    ) {}

    /** @param list<string> $symbols */
    public function handle(
        int $days = 90,
        array $symbols = [],
        ?CarbonImmutable $rangeEnd = null,
    ): SyncRun {
        if ($days < 8 || $days > 365) {
            throw new InvalidArgumentException('The GDELT period must be between 8 and 365 days.');
        }

        $dataSource = DataSource::query()
            ->where('key', 'gdelt')
            ->where('is_active', true)
            ->firstOrFail();
        $normalizedSymbols = array_values(array_unique(array_map('strtoupper', $symbols)));
        $mappings = $this->mappings($dataSource, $normalizedSymbols);

        if ($mappings->isEmpty()) {
            throw new InvalidArgumentException('No active GDELT asset mappings matched the request.');
        }

        $rangeEnd = ($rangeEnd ?? CarbonImmutable::now('UTC'))->startOfDay();
        $rangeStart = $rangeEnd->subDays($days);
        $syncRun = $dataSource->syncRuns()->create([
            'status' => SyncRunStatus::Running,
            'started_at' => now('UTC'),
            'context' => [
                'days' => $days,
                'symbols' => $normalizedSymbols,
                'range_start' => $rangeStart->toIso8601String(),
                'range_end' => $rangeEnd->toIso8601String(),
                'modes' => ['TimelineVolRaw', 'TimelineTone'],
            ],
        ]);
        $recordsReceived = 0;
        $recordsStored = 0;
        $hasRequested = false;

        try {
            foreach ($mappings as $mapping) {
                $this->waitForRateLimit($hasRequested);
                $volumePoints = $this->fetchGdeltTimeline->handle(
                    $mapping->external_identifier,
                    'TimelineVolRaw',
                    $rangeStart,
                    $rangeEnd,
                );
                $hasRequested = true;
                $this->waitForRateLimit($hasRequested);
                $tonePoints = $this->fetchGdeltTimeline->handle(
                    $mapping->external_identifier,
                    'TimelineTone',
                    $rangeStart,
                    $rangeEnd,
                );
                $recordsReceived += count($volumePoints) + count($tonePoints);
                $recordsStored += $this->store(
                    $mapping,
                    $dataSource,
                    $syncRun,
                    $volumePoints,
                    $tonePoints,
                );
            }

            $syncRun->update([
                'status' => SyncRunStatus::Completed,
                'finished_at' => now('UTC'),
                'records_received' => $recordsReceived,
                'records_stored' => $recordsStored,
            ]);
        } catch (Throwable $exception) {
            $syncRun->update([
                'status' => SyncRunStatus::Failed,
                'finished_at' => now('UTC'),
                'records_received' => $recordsReceived,
                'records_stored' => $recordsStored,
                'error_message' => Str::limit($exception->getMessage(), 2000),
            ]);

            throw $exception;
        }

        return $syncRun->refresh();
    }

    private function waitForRateLimit(bool $hasRequested): void
    {
        if ($hasRequested) {
            Sleep::for((int) config('services.gdelt.request_interval_seconds'))->seconds();
        }
    }

    /**
     * @param  list<array{observed_on: CarbonImmutable, value: float, norm: int|null, source_payload: array<string, mixed>}>  $volumePoints
     * @param  list<array{observed_on: CarbonImmutable, value: float, norm: int|null, source_payload: array<string, mixed>}>  $tonePoints
     */
    private function store(
        AssetSourceMapping $mapping,
        DataSource $dataSource,
        SyncRun $syncRun,
        array $volumePoints,
        array $tonePoints,
    ): int {
        $this->storeSourceRecords->handle(
            $mapping,
            $dataSource,
            $syncRun,
            'news_timeline_volume',
            $this->rawRecords($mapping, 'TimelineVolRaw', $volumePoints),
        );
        $this->storeSourceRecords->handle(
            $mapping,
            $dataSource,
            $syncRun,
            'news_timeline_tone',
            $this->rawRecords($mapping, 'TimelineTone', $tonePoints),
        );
        $toneByDate = collect($tonePoints)->keyBy(
            fn (array $point): string => $point['observed_on']->toDateString(),
        );
        $timestamp = now();
        $rows = [];

        foreach ($volumePoints as $point) {
            $date = $point['observed_on']->toDateString();
            $norm = $point['norm'];
            $articleCount = max(0, (int) round($point['value']));
            $tone = $toneByDate->get($date);
            $rows[] = [
                'asset_id' => $mapping->asset_id,
                'data_source_id' => $dataSource->id,
                'observed_on' => $date,
                'article_count' => $articleCount,
                'monitored_article_count' => $norm,
                'coverage_share_pct' => $norm === null || $norm === 0
                    ? null
                    : ($articleCount / $norm) * 100,
                'average_tone' => is_array($tone) ? $tone['value'] : null,
                'query' => $mapping->external_identifier,
                'provider_version' => 'gdelt-doc-2',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($rows === []) {
            return 0;
        }

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

        return count($rows);
    }

    /**
     * @param  list<array{observed_on: CarbonImmutable, value: float, norm: int|null, source_payload: array<string, mixed>}>  $points
     * @return list<array{provider_key: string, observed_at: CarbonImmutable, payload: array<string, mixed>}>
     */
    private function rawRecords(
        AssetSourceMapping $mapping,
        string $mode,
        array $points,
    ): array {
        $queryHash = hash('sha1', $mapping->external_identifier);

        return array_map(
            fn (array $point): array => [
                'provider_key' => implode(':', [
                    $queryHash,
                    $mode,
                    $point['observed_on']->toDateString(),
                ]),
                'observed_at' => $point['observed_on'],
                'payload' => [
                    'query' => $mapping->external_identifier,
                    'mode' => $mode,
                    'point' => $point['source_payload'],
                ],
            ],
            $points,
        );
    }

    /**
     * @param  list<string>  $symbols
     * @return Collection<int, AssetSourceMapping>
     */
    private function mappings(DataSource $dataSource, array $symbols): Collection
    {
        return $dataSource->assetMappings()
            ->where('is_active', true)
            ->whereHas('asset', fn ($query) => $query
                ->where('is_active', true)
                ->when($symbols !== [], fn ($assetQuery) => $assetQuery->whereIn('symbol', $symbols)))
            ->orderBy('id')
            ->get();
    }
}
