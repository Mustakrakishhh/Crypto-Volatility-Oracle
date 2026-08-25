<?php

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $symbol
 * @property string $name
 * @property string $base_currency
 * @property string $quote_currency
 * @property int $display_order
 * @property bool $is_active
 * @property-read Collection<int, AssetSourceMapping> $sourceMappings
 * @property-read Collection<int, MarketCandle> $marketCandles
 * @property-read MarketCandle|null $latestMarketCandle
 * @property-read Collection<int, FeatureSnapshot> $featureSnapshots
 * @property-read Collection<int, VolatilityForecast> $volatilityForecasts
 * @property-read Collection<int, NewsFeatureSnapshot> $newsFeatureSnapshots
 * @property-read Collection<int, SourceRecord> $sourceRecords
 */
#[Fillable(['symbol', 'name', 'base_currency', 'quote_currency', 'display_order', 'is_active'])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'display_order' => 0,
        'is_active' => true,
    ];

    /** @return HasMany<AssetSourceMapping, $this> */
    public function sourceMappings(): HasMany
    {
        return $this->hasMany(AssetSourceMapping::class);
    }

    /** @return HasMany<MarketCandle, $this> */
    public function marketCandles(): HasMany
    {
        return $this->hasMany(MarketCandle::class);
    }

    /** @return HasOne<MarketCandle, $this> */
    public function latestMarketCandle(): HasOne
    {
        return $this->hasOne(MarketCandle::class)->latestOfMany('opened_at');
    }

    /** @return HasMany<FeatureSnapshot, $this> */
    public function featureSnapshots(): HasMany
    {
        return $this->hasMany(FeatureSnapshot::class);
    }

    /** @return HasMany<VolatilityForecast, $this> */
    public function volatilityForecasts(): HasMany
    {
        return $this->hasMany(VolatilityForecast::class);
    }

    /** @return HasMany<NewsFeatureSnapshot, $this> */
    public function newsFeatureSnapshots(): HasMany
    {
        return $this->hasMany(NewsFeatureSnapshot::class);
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
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
