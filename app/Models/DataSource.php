<?php

namespace App\Models;

use App\Enums\DataSourceType;
use App\Enums\SyncRunStatus;
use Database\Factories\DataSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property DataSourceType $type
 * @property string $base_url
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property-read Collection<int, AssetSourceMapping> $assetMappings
 * @property-read Collection<int, SyncRun> $syncRuns
 * @property-read Collection<int, MarketCandle> $marketCandles
 * @property-read Collection<int, FeatureSnapshot> $featureSnapshots
 * @property-read Collection<int, NewsFeatureSnapshot> $newsFeatureSnapshots
 * @property-read Collection<int, SourceRecord> $sourceRecords
 * @property-read SyncRun|null $latestSyncRun
 * @property-read SyncRun|null $latestCompletedSyncRun
 */
#[Fillable(['key', 'name', 'type', 'base_url', 'is_active', 'metadata'])]
class DataSource extends Model
{
    /** @use HasFactory<DataSourceFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /** @return HasMany<AssetSourceMapping, $this> */
    public function assetMappings(): HasMany
    {
        return $this->hasMany(AssetSourceMapping::class);
    }

    /** @return HasMany<SyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    /** @return HasMany<MarketCandle, $this> */
    public function marketCandles(): HasMany
    {
        return $this->hasMany(MarketCandle::class);
    }

    /** @return HasMany<FeatureSnapshot, $this> */
    public function featureSnapshots(): HasMany
    {
        return $this->hasMany(FeatureSnapshot::class);
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

    /** @return HasOne<SyncRun, $this> */
    public function latestSyncRun(): HasOne
    {
        return $this->hasOne(SyncRun::class)->latestOfMany('started_at');
    }

    /** @return HasOne<SyncRun, $this> */
    public function latestCompletedSyncRun(): HasOne
    {
        return $this->hasOne(SyncRun::class)
            ->ofMany(
                ['started_at' => 'max', 'id' => 'max'],
                fn (Builder $query) => $query->where('status', SyncRunStatus::Completed),
            );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => DataSourceType::class,
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
