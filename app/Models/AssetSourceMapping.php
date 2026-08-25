<?php

namespace App\Models;

use Database\Factories\AssetSourceMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $asset_id
 * @property int $data_source_id
 * @property string $external_identifier
 * @property array<string, mixed>|null $settings
 * @property bool $is_active
 * @property-read Asset|null $asset
 * @property-read DataSource|null $dataSource
 */
#[Fillable(['asset_id', 'data_source_id', 'external_identifier', 'settings', 'is_active'])]
class AssetSourceMapping extends Model
{
    /** @use HasFactory<AssetSourceMappingFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
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
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
