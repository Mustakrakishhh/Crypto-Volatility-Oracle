<?php

use App\Enums\SyncRunStatus;
use App\Models\MarketCandle;
use App\Models\SourceRecord;
use App\Models\SyncRun;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-24 18:30:00 UTC');
    Http::preventStrayRequests();
    $this->seed(DatabaseSeeder::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('it backfills historical candles across advancing Bybit pages', function () {
    $requestCount = 0;
    Http::fake(function () use (&$requestCount) {
        $requestCount++;

        return Http::response(backfillKlinePayload(
            $requestCount === 1
                ? [
                    ['1787590800000', '100', '102', '99', '101', '10', '1010'],
                    ['1787587200000', '99', '101', '98', '100', '10', '1000'],
                ]
                : [
                    ['1787583600000', '98', '100', '97', '99', '10', '990'],
                ],
        ));
    });

    $this->artisan('oracle:backfill-bybit', [
        '--days' => 2,
        '--page-size' => 2,
        '--symbol' => ['BTCUSDT'],
    ])
        ->expectsOutputToContain('3 candles processed across 2 pages')
        ->assertSuccessful();

    expect(MarketCandle::query()->count())->toBe(3)
        ->and(SourceRecord::query()->count())->toBe(3)
        ->and(SyncRun::query()->sole()->status)->toBe(SyncRunStatus::Completed)
        ->and(SyncRun::query()->sole()->context['mode'])->toBe('backfill')
        ->and(SyncRun::query()->sole()->context['pages'])->toBe(2);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request['symbol'] === 'BTCUSDT'
        && $request['limit'] === 2
        && is_int($request['start'])
        && is_int($request['end']));
});

/**
 * @param  list<array<int, string>>  $rows
 * @return array<string, mixed>
 */
function backfillKlinePayload(array $rows): array
{
    return [
        'retCode' => 0,
        'retMsg' => 'OK',
        'result' => [
            'category' => 'linear',
            'symbol' => 'BTCUSDT',
            'list' => $rows,
        ],
        'time' => 1787596200000,
    ];
}
