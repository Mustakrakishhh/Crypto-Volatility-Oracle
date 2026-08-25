<?php

namespace App\Actions;

use App\Enums\SyncRunStatus;
use App\Models\AssetSourceMapping;
use App\Models\DataSource;
use App\Models\NewsFeatureSnapshot;
use App\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class SyncAlphaVantageNewsFeatures
{
    public function __construct(
        private readonly FetchAlphaVantageNews $fetchAlphaVantageNews,
        private readonly StoreSourceRecords $storeSourceRecords,
    ) {}

    /** @param list<string> $symbols */
    public function handle(
        int $days = 30,
        array $symbols = [],
        ?CarbonImmutable $rangeEnd = null,
    ): SyncRun {
        if ($days < 1 || $days > 365) {
            throw new InvalidArgumentException('The Alpha Vantage period must be between 1 and 365 days.');
        }

        $dataSource = DataSource::query()
            ->where('key', 'alpha-vantage')
            ->where('is_active', true)
            ->firstOrFail();
        $normalizedSymbols = array_values(array_unique(array_map('strtoupper', $symbols)));
        $mappings = $this->mappings($dataSource, $normalizedSymbols);

        if ($mappings->isEmpty()) {
            throw new InvalidArgumentException('No active Alpha Vantage asset mappings matched the request.');
        }

        $rangeEnd = ($rangeEnd ?? CarbonImmutable::now('UTC'))->endOfDay();
        $rangeStart = $rangeEnd->startOfDay()->subDays($days);
        $syncRun = $dataSource->syncRuns()->create([
            'status' => SyncRunStatus::Running,
            'started_at' => now('UTC'),
            'context' => [
                'days' => $days,
                'symbols' => $normalizedSymbols,
                'range_start' => $rangeStart->toIso8601String(),
                'range_end' => $rangeEnd->toIso8601String(),
                'function' => 'NEWS_SENTIMENT',
            ],
        ]);
        $recordsReceived = 0;
        $recordsStored = 0;

        try {
            foreach ($mappings as $mapping) {
                $articles = $this->fetchAlphaVantageNews->handle(
                    $mapping->external_identifier,
                    $rangeStart,
                    $rangeEnd,
                );
                $recordsReceived += count($articles);
                $recordsStored += $this->store($mapping, $dataSource, $syncRun, $articles);
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

    /**
     * @param list<array{
     *     provider_key: string,
     *     observed_at: CarbonImmutable,
     *     sentiment: float,
     *     relevance: float,
     *     source_payload: array<string, mixed>
     * }> $articles
     */
    private function store(
        AssetSourceMapping $mapping,
        DataSource $dataSource,
        SyncRun $syncRun,
        array $articles,
    ): int {
        $this->storeSourceRecords->handle(
            $mapping,
            $dataSource,
            $syncRun,
            'news_article_sentiment',
            array_map(fn (array $article): array => [
                'provider_key' => $article['provider_key'],
                'observed_at' => $article['observed_at'],
                'payload' => $article['source_payload'],
            ], $articles),
        );

        $articlesByDate = collect($articles)->groupBy(
            fn (array $article): string => $article['observed_at']->toDateString(),
        );
        $timestamp = now('UTC');
        $rows = [];

        foreach ($articlesByDate as $date => $dailyArticles) {
            $totalWeight = (float) $dailyArticles->sum('relevance');
            $weightedSentiment = $totalWeight > 0
                ? (float) $dailyArticles->sum(
                    fn (array $article): float => $article['sentiment'] * $article['relevance'],
                ) / $totalWeight
                : (float) $dailyArticles->average('sentiment');
            $rows[] = [
                'asset_id' => $mapping->asset_id,
                'data_source_id' => $dataSource->id,
                'observed_on' => $date,
                'article_count' => $dailyArticles->count(),
                'monitored_article_count' => null,
                'coverage_share_pct' => null,
                'average_tone' => $weightedSentiment,
                'query' => $mapping->external_identifier,
                'provider_version' => 'alpha-vantage-news-sentiment',
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
            ['article_count', 'average_tone', 'query', 'provider_version', 'updated_at'],
        );

        return count($rows);
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
