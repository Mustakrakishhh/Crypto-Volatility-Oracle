<?php

namespace App\Models;

use Database\Factories\FeatureSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $asset_id
 * @property int $data_source_id
 * @property Carbon $calculated_at
 * @property int $horizon_hours
 * @property string $price
 * @property string $change_pct
 * @property string $realized_volatility_pct
 * @property int $risk_score
 * @property string $risk_level
 * @property int $data_completeness
 * @property array<string, mixed> $features
 * @property string $algorithm_version
 * @property-read Asset|null $asset
 * @property-read DataSource|null $dataSource
 * @property-read Collection<int, VolatilityForecast> $forecasts
 */
#[Fillable([
    'asset_id',
    'data_source_id',
    'calculated_at',
    'horizon_hours',
    'price',
    'change_pct',
    'realized_volatility_pct',
    'risk_score',
    'risk_level',
    'data_completeness',
    'features',
    'algorithm_version',
])]
class FeatureSnapshot extends Model
{
    /** @use HasFactory<FeatureSnapshotFactory> */
    use HasFactory;

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

    /** @return HasMany<VolatilityForecast, $this> */
    public function forecasts(): HasMany
    {
        return $this->hasMany(VolatilityForecast::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'calculated_at' => 'datetime',
            'horizon_hours' => 'integer',
            'price' => 'decimal:18',
            'change_pct' => 'decimal:6',
            'realized_volatility_pct' => 'decimal:6',
            'risk_score' => 'integer',
            'data_completeness' => 'integer',
            'features' => 'array',
        ];
    }
}
