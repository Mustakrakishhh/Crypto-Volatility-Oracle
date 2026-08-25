<?php

namespace App\Actions;

use App\Enums\SyncRunStatus;
use App\Models\AssetSourceMapping;
use App\Models\DataSource;
use App\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class BackfillBybitMarketData
{
    public function __construct(
        private readonly FetchBybitKlines $fetchBybitKlines,
        private readonly StoreMarketCandles $storeMarketCandles,
    ) {}

    /** @param list<string> $symbols */
    public function handle(int $days = 90, array $symbols = [], int $pageSize = 1000): SyncRun
    {
        if ($days < 2 || $days > 365) {
            throw new InvalidArgumentException('The backfill period must be between 2 and 365 days.');
        }

        if ($pageSize < 1 || $pageSize > 1000) {
            throw new InvalidArgumentException('The page size must be between 1 and 1000.');
        }

        $dataSource = DataSource::query()
            ->where('key', 'bybit')
            ->where('is_active', true)
            ->firstOrFail();
        $normalizedSymbols = array_values(array_unique(array_map('strtoupper', $symbols)));
        $mappings = $this->mappings($dataSource, $normalizedSymbols);

        if ($mappings->isEmpty()) {
            throw new InvalidArgumentException('No active Bybit asset mappings matched the request.');
        }

        $rangeEnd = CarbonImmutable::now('UTC')->startOfHour()->subMillisecond();
        $rangeStart = $rangeEnd->addMillisecond()->subDays($days);
        $syncRun = $dataSource->syncRuns()->create([
            'status' => SyncRunStatus::Running,
            'started_at' => now('UTC'),
            'context' => [
                'mode' => 'backfill',
                'days' => $days,
                'page_size' => $pageSize,
                'symbols' => $normalizedSymbols,
                'range_start' => $rangeStart->toIso8601String(),
                'range_end' => $rangeEnd->toIso8601String(),
            ],
        ]);
        $recordsReceived = 0;
        $recordsStored = 0;
        $pages = 0;

        try {
            foreach ($mappings as $mapping) {
                $settings = $mapping->settings ?? [];
                $intervalMinutes = (int) ($settings['interval'] ?? 60);
                $category = (string) ($settings['category'] ?? 'linear');
                $cursorEnd = $rangeEnd;

                while ($cursorEnd->greaterThanOrEqualTo($rangeStart)) {
                    $candles = $this->fetchBybitKlines->handle(
                        $mapping->external_identifier,
                        $category,
                        $intervalMinutes,
                        $pageSize,
                        $rangeStart,
                        $cursorEnd,
                    );
                    $pages++;

                    if ($candles === []) {
                        break;
                    }

                    $recordsReceived += count($candles);
                    $recordsStored += $this->storeMarketCandles->handle(
                        $mapping,
                        $dataSource,
                        $syncRun,
                        $candles,
                        $intervalMinutes,
                    );

                    if (count($candles) < $pageSize) {
                        break;
                    }

                    $nextCursorEnd = $candles[0]['opened_at']->subMillisecond();

                    if (! $nextCursorEnd->lessThan($cursorEnd)) {
                        throw new RuntimeException('Bybit pagination cursor did not advance.');
                    }

                    $cursorEnd = $nextCursorEnd;
                }
            }

            $syncRun->update([
                'status' => SyncRunStatus::Completed,
                'finished_at' => now('UTC'),
                'records_received' => $recordsReceived,
                'records_stored' => $recordsStored,
                'context' => [...($syncRun->context ?? []), 'pages' => $pages],
            ]);
        } catch (Throwable $exception) {
            $syncRun->update([
                'status' => SyncRunStatus::Failed,
                'finished_at' => now('UTC'),
                'records_received' => $recordsReceived,
                'records_stored' => $recordsStored,
                'error_message' => Str::limit($exception->getMessage(), 2000),
                'context' => [...($syncRun->context ?? []), 'pages' => $pages],
            ]);

            throw $exception;
        }

        return $syncRun->refresh();
    }

    /**
     * @param  list<string>  $symbols
     * @return Collection<int, AssetSourceMapping>
     */
    private function mappings(DataSource $dataSource, array $symbols): Collection
    {
        return $dataSource->assetMappings()
            ->where('is_active', true)
            ->whereHas('asset', fn ($query) => $query->where('is_active', true))
            ->when(
                $symbols !== [],
                fn ($query) => $query->whereIn('external_identifier', $symbols),
            )
            ->orderBy('id')
            ->get();
    }
}
