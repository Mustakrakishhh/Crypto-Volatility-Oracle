<?php

namespace Database\Factories;

use App\Actions\BuildVolatilityBaseline;
use App\Models\Asset;
use App\Models\DataSource;
use App\Models\FeatureSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureSnapshot>
 */
class FeatureSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'data_source_id' => DataSource::factory(),
            'calculated_at' => now('UTC')->startOfHour(),
            'horizon_hours' => 24,
            'price' => (string) fake()->randomFloat(8, 100, 100000),
            'change_pct' => (string) fake()->randomFloat(4, -10, 10),
            'realized_volatility_pct' => (string) fake()->randomFloat(4, 0.1, 12),
            'risk_score' => fake()->numberBetween(0, 100),
            'risk_level' => fake()->randomElement(['low', 'moderate', 'high', 'extreme']),
            'data_completeness' => 100,
            'features' => ['observed_returns' => 24, 'expected_returns' => 24],
            'algorithm_version' => BuildVolatilityBaseline::FEATURE_VERSION,
        ];
    }
}
