<?php

use App\Actions\BuildVolatilityBaseline;
use App\Models\Asset;
use App\Models\DataSource;
use App\Models\FeatureSnapshot;
use App\Models\MarketCandle;
use App\Models\VolatilityForecast;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('it builds an idempotent persistence baseline and evaluates only known future windows', function () {
    $this->seed(DatabaseSeeder::class);

    $asset = Asset::query()->where('symbol', 'BTCUSDT')->sole();
    $source = DataSource::query()->where('key', 'bybit')->sole();
    $startsAt = now('UTC')->startOfHour()->subHours(73);

    for ($hour = 0; $hour < 73; $hour++) {
        $openedAt = $startsAt->copy()->addHours($hour);
        $price = 100 * (1 + (($hour < 49 ? 0.002 : 0.008) * $hour));
        MarketCandle::factory()->create([
            'asset_id' => $asset->id,
            'data_source_id' => $source->id,
            'opened_at' => $openedAt,
            'closed_at' => $openedAt->copy()->addHour(),
            'open' => (string) $price,
            'high' => (string) $price,
            'low' => (string) $price,
            'close' => (string) $price,
            'is_closed' => true,
        ]);
    }

    $arguments = ['--symbol' => ['BTCUSDT']];
    $this->artisan('oracle:build-baseline', $arguments)
        ->expectsOutputToContain('3 forecasts, 2 evaluated, 3 snapshots')
        ->assertSuccessful();

    expect(FeatureSnapshot::query()->count())->toBe(3)
        ->and(VolatilityForecast::query()->where('status', 'evaluated')->count())->toBe(2)
        ->and(VolatilityForecast::query()->where('status', 'pending')->count())->toBe(1);

    $latestForecast = VolatilityForecast::query()->latest('predicted_at')->firstOrFail();

    expect($latestForecast->model_version)->toBe(BuildVolatilityBaseline::MODEL_VERSION)
        ->and($latestForecast->model_inputs)->toMatchArray([
            'source' => 'bybit',
            'method' => 'persistence',
        ])
        ->and($latestForecast->model_inputs)->toHaveKeys([
            'observed_window_end',
            'realized_volatility_pct',
            'change_pct',
            'data_completeness',
        ])
        ->and($latestForecast->confidence_pct)->not->toBeNull()
        ->and($latestForecast->lower_bound_pct)->not->toBeNull()
        ->and($latestForecast->upper_bound_pct)->not->toBeNull()
        ->and($latestForecast->actual_volatility_pct)->toBeNull();

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('assets.0.forecast.status', 'ready')
        ->where('assets.0.forecast.model_version', BuildVolatilityBaseline::MODEL_VERSION)
        ->where('assets.0.forecast.risk_label', fn ($value) => is_string($value))
        ->where('assets.0.forecast.backtest.sample_size', 2)
        ->has('assets.0.forecast.arguments', 2)
        ->where('assets.0.forecast.arguments.0', fn ($value) => str_contains($value, 'Persistence'))
        ->has('assets.0.forecast.failure_risks', 3)
        ->where('assets.0.forecast.predicted_volatility', fn ($value) => is_float($value)));

    $this->artisan('oracle:build-baseline', $arguments)->assertSuccessful();

    expect(FeatureSnapshot::query()->count())->toBe(3)
        ->and(VolatilityForecast::query()->count())->toBe(3);
});
