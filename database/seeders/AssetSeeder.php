<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $assets = [
            ['symbol' => 'BTCUSDT', 'name' => 'Bitcoin', 'base_currency' => 'BTC', 'quote_currency' => 'USDT', 'display_order' => 10],
            ['symbol' => 'ETHUSDT', 'name' => 'Ethereum', 'base_currency' => 'ETH', 'quote_currency' => 'USDT', 'display_order' => 20],
            ['symbol' => 'SOLUSDT', 'name' => 'Solana', 'base_currency' => 'SOL', 'quote_currency' => 'USDT', 'display_order' => 30],
        ];

        foreach ($assets as $asset) {
            Asset::query()->updateOrCreate(
                ['symbol' => $asset['symbol']],
                [...$asset, 'is_active' => true],
            );
        }
    }
}
