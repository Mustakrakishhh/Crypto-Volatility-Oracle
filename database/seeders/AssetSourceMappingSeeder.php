<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetSourceMapping;
use App\Models\DataSource;
use Illuminate\Database\Seeder;

class AssetSourceMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bybit = DataSource::query()->where('key', 'bybit')->firstOrFail();
        $gdelt = DataSource::query()->where('key', 'gdelt')->firstOrFail();
        $alphaVantage = DataSource::query()->where('key', 'alpha-vantage')->firstOrFail();

        $newsQueries = [
            'BTCUSDT' => '(Bitcoin OR BTC)',
            'ETHUSDT' => '(Ethereum OR Ether OR ETH)',
            'SOLUSDT' => '(Solana OR SOL)',
        ];
        $alphaVantageTickers = [
            'BTCUSDT' => 'CRYPTO:BTC',
            'ETHUSDT' => 'CRYPTO:ETH',
            'SOLUSDT' => 'CRYPTO:SOL',
        ];

        Asset::query()->each(function (Asset $asset) use (
            $bybit,
            $gdelt,
            $alphaVantage,
            $newsQueries,
            $alphaVantageTickers,
        ): void {
            AssetSourceMapping::query()->updateOrCreate(
                ['asset_id' => $asset->id, 'data_source_id' => $bybit->id],
                [
                    'external_identifier' => $asset->symbol,
                    'settings' => ['category' => 'linear', 'interval' => '60'],
                    'is_active' => true,
                ],
            );

            AssetSourceMapping::query()->updateOrCreate(
                ['asset_id' => $asset->id, 'data_source_id' => $gdelt->id],
                [
                    'external_identifier' => $newsQueries[$asset->symbol],
                    'settings' => ['modes' => ['TimelineVolRaw', 'TimelineTone'], 'format' => 'json'],
                    'is_active' => true,
                ],
            );

            AssetSourceMapping::query()->updateOrCreate(
                ['asset_id' => $asset->id, 'data_source_id' => $alphaVantage->id],
                [
                    'external_identifier' => $alphaVantageTickers[$asset->symbol],
                    'settings' => ['function' => 'NEWS_SENTIMENT', 'limit' => 200],
                    'is_active' => true,
                ],
            );
        });
    }
}
