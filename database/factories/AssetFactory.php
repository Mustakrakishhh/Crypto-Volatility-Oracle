<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseCurrency = strtoupper(fake()->unique()->lexify('???'));

        return [
            'symbol' => $baseCurrency.'USDT',
            'name' => fake()->word(),
            'base_currency' => $baseCurrency,
            'quote_currency' => 'USDT',
            'display_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}
