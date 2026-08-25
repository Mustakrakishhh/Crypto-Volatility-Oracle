<?php

use App\Enums\SyncRunStatus;
use App\Models\Asset;
use App\Models\DataSource;
use App\Models\MarketCandle;
use App\Models\SyncRun;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('the public oracle dashboard exposes persisted reference data', function () {
    $this->seed(DatabaseSeeder::class);

    $response = $this->get(route('home'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('OracleDashboard')
        ->has('assets', 3)
        ->where('assets.0.symbol', 'BTCUSDT')
        ->where('assets.0.market.last_price', null)
        ->where('assets.0.analysis.status', 'waiting_for_history')
        ->where('assets.0.analysis.data_completeness', 0)
        ->where('assets.0.forecast.status', 'waiting_for_data')
        ->has('assets.0.sources', 3)
        ->has('sources', 3)
        ->where('overview.assets_count', 3)
        ->where('overview.sources_count', 3)
        ->where('overview.forecast_horizon_hours', 24)
        ->where('overview.data_state', 'waiting'));
});

test('the oracle dashboard does not require authentication', function () {
    $this->get(route('home'))->assertOk();
});

test('the oracle dashboard displays the latest persisted market candle', function () {
    $this->seed(DatabaseSeeder::class);

    $asset = Asset::query()->where('symbol', 'BTCUSDT')->sole();
    $source = DataSource::query()->where('key', 'bybit')->sole();

    MarketCandle::factory()->create([
        'asset_id' => $asset->id,
        'data_source_id' => $source->id,
        'opened_at' => now('UTC')->startOfHour(),
        'closed_at' => now('UTC')->startOfHour()->addHour(),
        'close' => '64250.125',
        'is_closed' => false,
    ]);

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('assets.0.market.last_price', '64250.125000000000000000')
        ->where('assets.0.analysis.status', 'waiting_for_history')
        ->where('assets.0.forecast.status', 'collecting_history'));
});

test('the oracle dashboard exposes complete 24 hour market metrics and risk factors', function () {
    $this->seed(DatabaseSeeder::class);

    $asset = Asset::query()->where('symbol', 'BTCUSDT')->sole();
    $source = DataSource::query()->where('key', 'bybit')->sole();
    $startsAt = now('UTC')->startOfHour()->subHours(24);

    for ($hour = 0; $hour <= 24; $hour++) {
        $openedAt = $startsAt->copy()->addHours($hour);
        MarketCandle::factory()->create([
            'asset_id' => $asset->id,
            'data_source_id' => $source->id,
            'opened_at' => $openedAt,
            'closed_at' => $openedAt->copy()->addHour(),
            'open' => '100',
            'high' => '100',
            'low' => '100',
            'close' => '100',
            'is_closed' => true,
        ]);
    }

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('assets.0.market.change_24h', 0)
        ->where('assets.0.market.volatility_24h', 0)
        ->where('assets.0.analysis.status', 'ready')
        ->where('assets.0.analysis.data_completeness', 100)
        ->where('assets.0.analysis.risk_score', 0)
        ->where('assets.0.analysis.risk_level', 'low')
        ->has('assets.0.analysis.factors', 2));
});

test('the source card keeps the latest successful data load when a newer sync attempt fails', function () {
    $this->seed(DatabaseSeeder::class);

    $source = DataSource::query()->where('key', 'gdelt')->sole();
    $successfulRun = SyncRun::factory()->create([
        'data_source_id' => $source->id,
        'status' => SyncRunStatus::Completed,
        'started_at' => now('UTC')->subDay(),
        'finished_at' => now('UTC')->subDay()->addMinutes(5),
        'records_received' => 522,
        'records_stored' => 261,
    ]);
    $failedRun = SyncRun::factory()->create([
        'data_source_id' => $source->id,
        'status' => SyncRunStatus::Failed,
        'started_at' => now('UTC')->subHour(),
        'finished_at' => now('UTC')->subHour()->addMinutes(2),
        'records_received' => 0,
        'records_stored' => 0,
        'error_message' => 'HTTP 429 Too Many Requests',
    ]);

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sources.1.status', 'error')
        ->where('sources.1.latest_sync.status', 'completed')
        ->where('sources.1.latest_sync.records_received', 522)
        ->where('sources.1.latest_sync.records_stored', 261)
        ->where('sources.1.latest_sync.started_at', $successfulRun->started_at->toIso8601String())
        ->where('sources.1.latest_attempt.status', 'failed')
        ->where('sources.1.latest_attempt.records_stored', 0)
        ->where('sources.1.latest_attempt.started_at', $failedRun->started_at->toIso8601String()));
});
