<?php

namespace App\Models;

use Database\Factories\MarketCandleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $asset_id
 * @property int $data_source_id
 * @property int $interval_minutes
 * @property Carbon $opened_at
 * @property Carbon $closed_at
 * @property string $open
 * @property string $high
 * @property string $low
 * @property string $close
 * @property string $volume
 * @property string $turnover
 * @property bool $is_closed
 * @property-read Asset|null $asset
 * @property-read DataSource|null $dataSource
 */
#[Fillable([
    'asset_id',
    'data_source_id',
    'interval_minutes',
    'opened_at',
    'closed_at',
    'open',
    'high',
    'low',
    'close',
    'volume',
    'turnover',
    'is_closed',
])]
class MarketCandle extends Model
{
    /** @use HasFactory<MarketCandleFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_closed' => false,
    ];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<DataSource, $this> */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'interval_minutes' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'open' => 'decimal:18',
            'high' => 'decimal:18',
            'low' => 'decimal:18',
            'close' => 'decimal:18',
            'volume' => 'decimal:18',
            'turnover' => 'decimal:18',
            'is_closed' => 'boolean',
        ];
    }
}
