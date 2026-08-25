<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\DataSource;
use App\Models\MarketCandle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketCandle>
 */
class MarketCandleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openedAt = now('UTC')->startOfHour()->subHours(fake()->numberBetween(1, 240));
        $open = fake()->randomFloat(8, 100, 100000);
        $close = $open * fake()->randomFloat(8, 0.98, 1.02);

        return [
            'asset_id' => Asset::factory(),
            'data_source_id' => DataSource::factory(),
            'interval_minutes' => 60,
            'opened_at' => $openedAt,
            'closed_at' => $openedAt->copy()->addHour(),
            'open' => (string) $open,
            'high' => (string) max($open, $close),
            'low' => (string) min($open, $close),
            'close' => (string) $close,
            'volume' => (string) fake()->randomFloat(8, 1, 10000),
            'turnover' => (string) fake()->randomFloat(8, 1000, 100000000),
            'is_closed' => true,
        ];
    }
}
