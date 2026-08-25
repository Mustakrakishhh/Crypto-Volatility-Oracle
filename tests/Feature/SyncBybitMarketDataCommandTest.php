<?php

use App\Enums\SyncRunStatus;
use App\Models\MarketCandle;
use App\Models\SourceRecord;
use App\Models\SyncRun;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed(DatabaseSeeder::class);
});

test('it synchronizes public hourly candles for every active Bybit asset', function () {
    Http::fake([
        'api.bybit.com/v5/market/kline*' => Http::response(bybitKlinePayload()),
    ]);

    $this->artisan('oracle:sync-bybit', ['--limit' => 2])
        ->expectsOutputToContain('6 of 6 candles stored')
        ->assertSuccessful();

    expect(MarketCandle::query()->count())->toBe(6)
        ->and(SourceRecord::query()->count())->toBe(6);

    $syncRun = SyncRun::query()->sole();

    expect($syncRun->status)->toBe(SyncRunStatus::Completed)
        ->and($syncRun->records_received)->toBe(6)
        ->and($syncRun->records_stored)->toBe(6)
        ->and($syncRun->finished_at)->not->toBeNull()
        ->and(SourceRecord::query()->firstOrFail()->payload)->toHaveKeys([
            'symbol',
            'start_time_ms',
            'close',
            'volume',
        ]);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.bybit.com/v5/market/kline?')
        && $request['category'] === 'linear'
        && $request['interval'] === '60'
        && $request['limit'] === 2
        && in_array($request['symbol'], ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'], true)
    );
});

test('repeated synchronization updates candles instead of duplicating them', function () {
    $requestCount = 0;
    Http::fake(function () use (&$requestCount) {
        $requestCount++;

        return Http::response(bybitKlinePayload($requestCount <= 3 ? '64000.50' : '65000.75'));
    });

    $this->artisan('oracle:sync-bybit', ['--limit' => 2])->assertSuccessful();

    $this->artisan('oracle:sync-bybit', ['--limit' => 2])->assertSuccessful();

    expect(MarketCandle::query()->count())->toBe(6)
        ->and(SourceRecord::query()->count())->toBe(6)
        ->and(MarketCandle::query()->get()->filter(
            fn (MarketCandle $candle): bool => (float) $candle->close === 65000.75,
        )->count())->toBe(3)
        ->and(SyncRun::query()->where('status', SyncRunStatus::Completed)->count())->toBe(2);
});

test('it records a failed run when Bybit rejects the request', function () {
    Http::fake([
        'api.bybit.com/v5/market/kline*' => Http::response([
            'retCode' => 10006,
            'retMsg' => 'Too many visits',
            'result' => [],
        ]),
    ]);

    $this->artisan('oracle:sync-bybit', ['--limit' => 2])
        ->expectsOutputToContain('Bybit synchronization failed')
        ->assertFailed();

    $syncRun = SyncRun::query()->sole();

    expect($syncRun->status)->toBe(SyncRunStatus::Failed)
        ->and($syncRun->error_message)->toContain('Too many visits')
        ->and(MarketCandle::query()->count())->toBe(0);
});

/** @return array<string, mixed> */
function bybitKlinePayload(string $latestClose = '64000.50'): array
{
    return [
        'retCode' => 0,
        'retMsg' => 'OK',
        'result' => [
            'category' => 'linear',
            'symbol' => 'BTCUSDT',
            'list' => [
                ['1787562000000', '63800.00', '64100.00', '63700.00', $latestClose, '123.45', '7900000.00'],
                ['1787558400000', '63500.00', '63900.00', '63400.00', '63800.00', '100.00', '6370000.00'],
            ],
        ],
        'time' => 1787565600000,
    ];
}
