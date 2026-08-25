<?php

namespace App\Actions;

use App\Models\AssetSourceMapping;
use App\Models\DataSource;
use App\Models\SourceRecord;
use App\Models\SyncRun;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class StoreSourceRecords
{
    /**
     * @param  list<array{
     *     provider_key: string,
     *     observed_at: CarbonInterface,
     *     payload: array<string, mixed>
     * }>  $records
     */
    public function handle(
        AssetSourceMapping $mapping,
        DataSource $dataSource,
        SyncRun $syncRun,
        string $recordType,
        array $records,
    ): int {
        if ($records === []) {
            return 0;
        }

        $timestamp = now('UTC');
        $rows = [];

        foreach ($records as $record) {
            $payload = json_encode($record['payload'], JSON_THROW_ON_ERROR);
            $rows[] = [
                'data_source_id' => $dataSource->id,
                'asset_id' => $mapping->asset_id,
                'sync_run_id' => $syncRun->id,
                'record_type' => $recordType,
                'provider_key' => $record['provider_key'],
                'observed_at' => $record['observed_at'],
                'payload' => $payload,
                'checksum' => hash('sha256', $payload),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::transaction(function () use ($rows): void {
            SourceRecord::query()->upsert(
                $rows,
                ['data_source_id', 'record_type', 'provider_key'],
                ['asset_id', 'sync_run_id', 'observed_at', 'payload', 'checksum', 'updated_at'],
            );
        });

        return count($rows);
    }
}
