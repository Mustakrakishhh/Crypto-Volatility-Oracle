<?php

use App\Enums\DataSourceType;
use App\Models\Asset;
use App\Models\DataSource;
use Database\Seeders\DatabaseSeeder;

test('it stores the supported assets and public data sources', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Asset::query()->orderBy('display_order')->pluck('symbol')->all())
        ->toBe(['BTCUSDT', 'ETHUSDT', 'SOLUSDT'])
        ->and(DataSource::query()->count())->toBe(3)
        ->and(DataSource::query()->where('key', 'bybit')->firstOrFail()->type)->toBe(DataSourceType::Market)
        ->and(DataSource::query()->where('key', 'gdelt')->firstOrFail()->type)->toBe(DataSourceType::News)
        ->and(DataSource::query()->where('key', 'alpha-vantage')->firstOrFail()->type)->toBe(DataSourceType::News);
});

test('it maps every supported asset to both data sources', function () {
    $this->seed(DatabaseSeeder::class);

    $bitcoin = Asset::query()->where('symbol', 'BTCUSDT')->firstOrFail();

    expect($bitcoin->sourceMappings)->toHaveCount(3)
        ->and($bitcoin->sourceMappings->pluck('external_identifier')->all())
        ->toContain('BTCUSDT', '(Bitcoin OR BTC)', 'CRYPTO:BTC');
});

test('reference data seeding is idempotent', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Asset::query()->count())->toBe(3)
        ->and(DataSource::query()->count())->toBe(3)
        ->and(Asset::query()->withCount('sourceMappings')->get()->pluck('source_mappings_count')->all())
        ->toBe([3, 3, 3]);
});
