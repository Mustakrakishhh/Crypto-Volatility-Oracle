<?php

namespace Database\Seeders;

use App\Enums\DataSourceType;
use App\Models\DataSource;
use Illuminate\Database\Seeder;

class DataSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            [
                'key' => 'bybit',
                'name' => 'Bybit Market API',
                'type' => DataSourceType::Market,
                'base_url' => 'https://api.bybit.com',
                'metadata' => ['documentation_url' => 'https://bybit-exchange.github.io/docs/v5/market'],
            ],
            [
                'key' => 'gdelt',
                'name' => 'GDELT DOC 2.0',
                'type' => DataSourceType::News,
                'base_url' => 'https://api.gdeltproject.org',
                'metadata' => ['documentation_url' => 'https://blog.gdeltproject.org/gdelt-doc-2-0-api-debuts/'],
            ],
            [
                'key' => 'alpha-vantage',
                'name' => 'Alpha Vantage News & Sentiment',
                'type' => DataSourceType::News,
                'base_url' => 'https://www.alphavantage.co',
                'metadata' => [
                    'documentation_url' => 'https://www.alphavantage.co/documentation/#news-sentiment',
                    'role' => 'fallback',
                ],
            ],
        ];

        foreach ($sources as $source) {
            DataSource::query()->updateOrCreate(
                ['key' => $source['key']],
                [...$source, 'is_active' => true],
            );
        }
    }
}
