<?php

use App\Actions\StoreSourceRecords;
use App\Enums\SyncRunStatus;
use App\Models\Asset;
use App\Models\DataSource;
use App\Models\SourceRecord;
use Database\Seeders\DatabaseSeeder;

test('it stores public source payloads idempotently and links them to a sync run', function () {
    $this->seed(DatabaseSeeder::class);

    $asset = Asset::query()->where('symbol', 'BTCUSDT')->sole();
    $source = DataSource::query()->where('key', 'bybit')->sole();
    $mapping = $source->assetMappings()->whereBelongsTo($asset)->sole();
    $firstRun = $source->syncRuns()->create([
        'status' => SyncRunStatus::Running,
        'started_at' => now('UTC'),
    ]);
    $records = [[
        'provider_key' => 'BTCUSDT:60:1787558400000',
        'observed_at' => now('UTC')->startOfHour(),
        'payload' => ['close' => '63800.00', 'volume' => '100.00'],
    ]];

    $stored = app(StoreSourceRecords::class)->handle(
        $mapping,
        $source,
        $firstRun,
        'market_candle',
        $records,
    );
    $sourceRecord = SourceRecord::query()->sole();

    expect($stored)->toBe(1)
        ->and($sourceRecord->sync_run_id)->toBe($firstRun->id)
        ->and($sourceRecord->payload)->toBe($records[0]['payload'])
        ->and($sourceRecord->checksum)->toBe(
            hash('sha256', json_encode($records[0]['payload'], JSON_THROW_ON_ERROR)),
        );

    $secondRun = $source->syncRuns()->create([
        'status' => SyncRunStatus::Running,
        'started_at' => now('UTC')->addMinute(),
    ]);
    $records[0]['payload']['close'] = '64000.00';

    app(StoreSourceRecords::class)->handle(
        $mapping,
        $source,
        $secondRun,
        'market_candle',
        $records,
    );

    expect(SourceRecord::query()->count())->toBe(1)
        ->and($sourceRecord->refresh()->sync_run_id)->toBe($secondRun->id)
        ->and($sourceRecord->payload['close'])->toBe('64000.00');
});
