<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\DataSource;
use App\Models\SourceRecord;
use App\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceRecord>
 */
class SourceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = ['date' => '20260824T000000Z', 'value' => 120, 'norm' => 120000];

        return [
            'data_source_id' => DataSource::factory(),
            'asset_id' => Asset::factory(),
            'sync_run_id' => SyncRun::factory(),
            'record_type' => 'news_timeline_volume',
            'provider_key' => fake()->uuid(),
            'observed_at' => now('UTC'),
            'payload' => $payload,
            'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
    }
}
