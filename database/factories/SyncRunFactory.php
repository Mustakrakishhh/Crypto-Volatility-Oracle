<?php

namespace Database\Factories;

use App\Enums\SyncRunStatus;
use App\Models\DataSource;
use App\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncRun>
 */
class SyncRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data_source_id' => DataSource::factory(),
            'status' => SyncRunStatus::Pending,
            'started_at' => now(),
            'finished_at' => null,
            'records_received' => 0,
            'records_stored' => 0,
            'error_message' => null,
            'context' => null,
        ];
    }
}
