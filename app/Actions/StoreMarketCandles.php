<?php

namespace App\Actions;

use App\Models\AssetSourceMapping;
use App\Models\DataSource;
use App\Models\MarketCandle;
use App\Models\SyncRun;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreMarketCandles
{
    public function __construct(private readonly StoreSourceRecords $storeSourceRecords) {}

    /**
     * @param  list<array<string, mixed>>  $candles
     */
    public function handle(
        AssetSourceMapping $mapping,
        DataSource $dataSource,
        SyncRun $syncRun,
        array $candles,
        int $intervalMinutes,
    ): int {
        if ($candles === []) {
            return 0;
        }

        $now = now('UTC');
        $rows = [];
        $sourceRecords = [];

        foreach ($candles as $candle) {
            $rows[] = [
                ...Arr::except($candle, ['source_payload']),
                'asset_id' => $mapping->asset_id,
                'data_source_id' => $dataSource->id,
                'interval_minutes' => $intervalMinutes,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $sourceRecords[] = [
                'provider_key' => implode(':', [
                    $mapping->external_identifier,
                    $intervalMinutes,
                    $candle['opened_at']->getTimestampMs(),
                ]),
                'observed_at' => $candle['opened_at'],
                'payload' => $candle['source_payload'],
            ];
        }

        $this->storeSourceRecords->handle(
            $mapping,
            $dataSource,
            $syncRun,
            'market_candle',
            $sourceRecords,
        );

        DB::transaction(function () use ($rows): void {
            MarketCandle::query()->upsert(
                $rows,
                ['asset_id', 'data_source_id', 'interval_minutes', 'opened_at'],
                ['closed_at', 'open', 'high', 'low', 'close', 'volume', 'turnover', 'is_closed', 'updated_at'],
            );
        });

        return count($rows);
    }
}
