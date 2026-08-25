<?php

use App\Actions\CalculateMarketMetrics;
use App\Models\MarketCandle;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

uses(TestCase::class);

test('it calculates complete 24 hour realized volatility and transparent risk factors', function () {
    $candles = hourlyCandles(25, fn (int $hour): float => 100 * (1.01 ** $hour));

    $metrics = app(CalculateMarketMetrics::class)->handle($candles);

    expect($metrics)
        ->change_24h->toBe(26.97)
        ->volatility_24h->toBe(4.87)
        ->data_completeness->toBe(100)
        ->observed_returns->toBe(24)
        ->expected_returns->toBe(24)
        ->risk_score->toBe(73)
        ->risk_level->toBe('high')
        ->risk_label->toBe('Высокий')
        ->and($metrics['factors'])->toHaveCount(2)
        ->and($metrics['factors'][0]['contribution'])->toBe(43)
        ->and($metrics['factors'][1]['contribution'])->toBe(30);
});

test('a stable complete series produces zero volatility and low risk', function () {
    $metrics = app(CalculateMarketMetrics::class)->handle(
        hourlyCandles(25, fn (): float => 100),
    );

    expect($metrics)
        ->change_24h->toBe(0.0)
        ->volatility_24h->toBe(0.0)
        ->data_completeness->toBe(100)
        ->risk_score->toBe(0)
        ->risk_level->toBe('low');
});

test('it refuses to label an incomplete candle series as a 24 hour metric', function () {
    $candles = hourlyCandles(25, fn (int $hour): float => 100 + $hour, missingHour: 12);

    $metrics = app(CalculateMarketMetrics::class)->handle($candles);

    expect($metrics)
        ->change_24h->toBeNull()
        ->volatility_24h->toBeNull()
        ->data_completeness->toBe(92)
        ->observed_returns->toBe(22)
        ->risk_score->toBeNull()
        ->factors->toBe([]);
});

/**
 * @param  callable(int): float  $priceAtHour
 * @return Collection<int, MarketCandle>
 */
function hourlyCandles(int $count, callable $priceAtHour, ?int $missingHour = null): Collection
{
    $startsAt = now('UTC')->startOfHour()->subHours($count - 1);
    $candles = [];

    for ($hour = 0; $hour < $count; $hour++) {
        if ($hour === $missingHour) {
            continue;
        }

        $openedAt = $startsAt->copy()->addHours($hour);
        $price = $priceAtHour($hour);
        $candles[] = new MarketCandle([
            'asset_id' => 1,
            'data_source_id' => 1,
            'interval_minutes' => 60,
            'opened_at' => $openedAt,
            'closed_at' => $openedAt->copy()->addHour(),
            'open' => (string) $price,
            'high' => (string) $price,
            'low' => (string) $price,
            'close' => (string) $price,
            'volume' => '1',
            'turnover' => (string) $price,
            'is_closed' => true,
        ]);
    }

    return new Collection($candles);
}
