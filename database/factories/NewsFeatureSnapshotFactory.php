<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\DataSource;
use App\Models\NewsFeatureSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsFeatureSnapshot>
 */
class NewsFeatureSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $monitoredArticleCount = fake()->numberBetween(100000, 500000);
        $articleCount = fake()->numberBetween(10, 500);

        return [
            'asset_id' => Asset::factory(),
            'data_source_id' => DataSource::factory(),
            'observed_on' => fake()->dateTimeBetween('-90 days', 'now'),
            'article_count' => $articleCount,
            'monitored_article_count' => $monitoredArticleCount,
            'coverage_share_pct' => (string) (($articleCount / $monitoredArticleCount) * 100),
            'average_tone' => (string) fake()->randomFloat(4, -8, 4),
            'query' => '(Bitcoin OR BTC)',
            'provider_version' => 'gdelt-doc-2',
        ];
    }
}
