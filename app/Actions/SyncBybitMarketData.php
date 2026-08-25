<?php

namespace App\Actions;

use App\Enums\SyncRunStatus;
use App\Models\AssetSourceMapping;
use App\Models\DataSource;
use App\Models\SyncRun;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class SyncBybitMarketData
{
    public function __construct(
        private readonly FetchBybitKlines $fetchBybitKlines,
        private readonly StoreMarketCandles $storeMarketCandles,
    ) {}

    /** @param list<string> $symbols */
    public function handle(int $limit = 200, array $symbols = []): SyncRun
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('The candle limit must be between 1 and 1000.');
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

        $syncRun = $dataSource->syncRuns()->create([
            'status' => SyncRunStatus::Running,
            'started_at' => now('UTC'),
            'context' => [
                'limit' => $limit,
                'symbols' => $normalizedSymbols,
                'interval_minutes' => 60,
            ],
        ]);

        $recordsReceived = 0;
        $recordsStored = 0;

        try {
            foreach ($mappings as $mapping) {
                $settings = $mapping->settings ?? [];
                $intervalMinutes = (int) ($settings['interval'] ?? 60);
                $category = (string) ($settings['category'] ?? 'linear');
                $candles = $this->fetchBybitKlines->handle(
                    $mapping->external_identifier,
                    $category,
                    $intervalMinutes,
                    $limit,
                );

                $recordsReceived += count($candles);
                $recordsStored += $this->storeMarketCandles->handle(
                    $mapping,
                    $dataSource,
                    $syncRun,
                    $candles,
                    $intervalMinutes,
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
