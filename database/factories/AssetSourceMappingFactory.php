<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetSourceMapping;
use App\Models\DataSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetSourceMapping>
 */
class AssetSourceMappingFactory extends Factory
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
            'external_identifier' => strtoupper(fake()->lexify('???')).'USDT',
            'settings' => null,
            'is_active' => true,
        ];
    }
}
