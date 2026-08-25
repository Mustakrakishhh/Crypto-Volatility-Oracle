<?php

namespace App\Models;

use Database\Factories\SourceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $data_source_id
 * @property int|null $asset_id
 * @property int|null $sync_run_id
 * @property string $record_type
 * @property string $provider_key
 * @property Carbon $observed_at
 * @property array<string, mixed> $payload
 * @property string $checksum
 */
#[Fillable([
    'data_source_id',
    'asset_id',
    'sync_run_id',
    'record_type',
    'provider_key',
    'observed_at',
    'payload',
    'checksum',
])]
class SourceRecord extends Model
{
    /** @use HasFactory<SourceRecordFactory> */
    use HasFactory;

    /** @return BelongsTo<DataSource, $this> */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<SyncRun, $this> */
    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
