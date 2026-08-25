<?php

namespace App\Models;

use Database\Factories\NewsFeatureSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $asset_id
 * @property int $data_source_id
 * @property Carbon $observed_on
 * @property int $article_count
 * @property int|null $monitored_article_count
 * @property string|null $coverage_share_pct
 * @property string|null $average_tone
 * @property string $query
 * @property string $provider_version
 */
#[Fillable([
    'asset_id',
    'data_source_id',
    'observed_on',
    'article_count',
    'monitored_article_count',
    'coverage_share_pct',
    'average_tone',
    'query',
    'provider_version',
])]
class NewsFeatureSnapshot extends Model
{
    /** @use HasFactory<NewsFeatureSnapshotFactory> */
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
            'article_count' => 'integer',
            'monitored_article_count' => 'integer',
            'coverage_share_pct' => 'decimal:8',
            'average_tone' => 'decimal:6',
        ];
    }
}
