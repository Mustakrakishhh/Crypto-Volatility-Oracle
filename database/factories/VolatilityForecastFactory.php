<?php

namespace Database\Factories;

use App\Actions\BuildVolatilityBaseline;
use App\Models\Asset;
use App\Models\FeatureSnapshot;
use App\Models\VolatilityForecast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolatilityForecast>
 */
class VolatilityForecastFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $predictedAt = now('UTC')->startOfHour();
        $predictedVolatility = fake()->randomFloat(4, 0.1, 12);

        return [
            'asset_id' => Asset::factory(),
            'feature_snapshot_id' => FeatureSnapshot::factory(),
            'predicted_at' => $predictedAt,
            'target_at' => $predictedAt->copy()->addDay(),
            'horizon_hours' => 24,
            'predicted_volatility_pct' => (string) $predictedVolatility,
            'status' => 'pending',
            'model_version' => BuildVolatilityBaseline::MODEL_VERSION,
        ];
    }
}
