<?php

namespace App\Models;

use App\Enums\SyncRunStatus;
use Database\Factories\SyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $data_source_id
 * @property SyncRunStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int $records_received
 * @property int $records_stored
 * @property string|null $error_message
 * @property array<string, mixed>|null $context
 * @property-read DataSource|null $dataSource
 * @property-read Collection<int, SourceRecord> $sourceRecords
 */
#[Fillable(['data_source_id', 'status', 'started_at', 'finished_at', 'records_received', 'records_stored', 'error_message', 'context'])]
class SyncRun extends Model
{
    /** @use HasFactory<SyncRunFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => SyncRunStatus::Pending,
        'records_received' => 0,
        'records_stored' => 0,
    ];

    /** @return BelongsTo<DataSource, $this> */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    /** @return HasMany<SourceRecord, $this> */
    public function sourceRecords(): HasMany
    {
        return $this->hasMany(SourceRecord::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SyncRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'records_received' => 'integer',
            'records_stored' => 'integer',
            'context' => 'array',
        ];
    }
}
