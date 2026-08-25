<?php

namespace Database\Factories;

use App\Enums\DataSourceType;
use App\Models\DataSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataSource>
 */
class DataSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'type' => fake()->randomElement(DataSourceType::cases()),
            'base_url' => fake()->url(),
            'is_active' => true,
            'metadata' => null,
        ];
    }
}
