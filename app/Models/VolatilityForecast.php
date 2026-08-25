<?php

namespace App\Models;

use Database\Factories\VolatilityForecastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $asset_id
 * @property int $feature_snapshot_id
 * @property Carbon $predicted_at
 * @property Carbon $target_at
 * @property int $horizon_hours
 * @property string $predicted_volatility_pct
 * @property string|null $lower_bound_pct
 * @property string|null $upper_bound_pct
 * @property string|null $confidence_pct
 * @property string $status
 * @property string|null $actual_volatility_pct
 * @property string|null $absolute_error
 * @property string|null $squared_error
 * @property Carbon|null $evaluated_at
 * @property string $model_version
 * @property array<string, mixed>|null $model_inputs
 * @property-read Asset|null $asset
 * @property-read FeatureSnapshot|null $featureSnapshot
 */
#[Fillable([
    'asset_id',
    'feature_snapshot_id',
    'predicted_at',
    'target_at',
    'horizon_hours',
    'predicted_volatility_pct',
    'lower_bound_pct',
    'upper_bound_pct',
    'confidence_pct',
    'status',
    'actual_volatility_pct',
    'absolute_error',
    'squared_error',
    'evaluated_at',
    'model_version',
    'model_inputs',
])]
class VolatilityForecast extends Model
{
    /** @use HasFactory<VolatilityForecastFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'horizon_hours' => 24,
        'status' => 'pending',
    ];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<FeatureSnapshot, $this> */
    public function featureSnapshot(): BelongsTo
    {
        return $this->belongsTo(FeatureSnapshot::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'predicted_at' => 'datetime',
            'target_at' => 'datetime',
            'horizon_hours' => 'integer',
            'predicted_volatility_pct' => 'decimal:6',
            'lower_bound_pct' => 'decimal:6',
            'upper_bound_pct' => 'decimal:6',
            'confidence_pct' => 'decimal:2',
            'actual_volatility_pct' => 'decimal:6',
            'absolute_error' => 'decimal:6',
            'squared_error' => 'decimal:8',
            'evaluated_at' => 'datetime',
            'model_inputs' => 'array',
        ];
    }
}
