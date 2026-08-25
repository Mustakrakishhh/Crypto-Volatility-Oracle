<?php

use App\Models\Asset;
use App\Models\DataSource;
use App\Models\FeatureSnapshot;
use App\Models\MarketCandle;
use App\Models\NewsFeatureSnapshot;
use App\Models\SourceRecord;
use App\Models\SyncRun;
use App\Models\VolatilityForecast;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\File;

test('it exports and idempotently loads a portable demo dataset', function () {
    $this->seed(DatabaseSeeder::class);

    $bybit = DataSource::query()->where('key', 'bybit')->sole();
    $gdelt = DataSource::query()->where('key', 'gdelt')->sole();
    $assets = Asset::query()->orderBy('display_order')->get();
    $startsAt = now('UTC')->startOfHour()->subHours(72);

    foreach ($assets as $assetIndex => $asset) {
        for ($hour = 0; $hour <= 72; $hour++) {
            $openedAt = $startsAt->copy()->addHours($hour);
            $price = 1000 + ($assetIndex * 500) + ($hour * 2);
            MarketCandle::factory()->create([
                'asset_id' => $asset->id,
                'data_source_id' => $bybit->id,
                'opened_at' => $openedAt,
                'closed_at' => $openedAt->copy()->addHour(),
                'open' => (string) $price,
                'high' => (string) ($price + 3),
                'low' => (string) ($price - 2),
                'close' => (string) ($price + 1),
                'is_closed' => true,
            ]);
        }

        for ($day = 0; $day <= 8; $day++) {
            $observedDate = now('UTC')->startOfDay()->subDays($day)->toDateString();
            $observedOn = CarbonImmutable::parse($observedDate, 'UTC')->addHours(12);
            NewsFeatureSnapshot::factory()->create([
                'asset_id' => $asset->id,
                'data_source_id' => $gdelt->id,
                'observed_on' => $observedOn,
                'article_count' => 100 + $day,
                'monitored_article_count' => 100000,
                'coverage_share_pct' => (string) ((100 + $day) / 1000),
                'average_tone' => '-1.500000',
            ]);
            SourceRecord::factory()->create([
                'data_source_id' => $gdelt->id,
                'asset_id' => $asset->id,
                'sync_run_id' => null,
                'provider_key' => "{$asset->symbol}:{$observedDate}",
                'observed_at' => $observedOn,
            ]);
        }

        SourceRecord::factory()->create([
            'data_source_id' => $bybit->id,
            'asset_id' => $asset->id,
            'sync_run_id' => null,
            'record_type' => 'market_kline',
            'provider_key' => "{$asset->symbol}:{$startsAt->toIso8601String()}",
            'observed_at' => $startsAt,
        ]);
    }

    $path = storage_path('framework/testing/oracle-demo-test.json');
    File::delete($path);

    $this->artisan('oracle:export-demo', ['--days' => 8, '--path' => $path])
        ->expectsOutputToContain('Demo dataset exported')
        ->assertSuccessful();

    $json = File::get($path);
    $dataset = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    $exportedRawCount = count($dataset['source_records']);
    $exportedNewsCount = count($dataset['news_feature_snapshots']);

    expect($dataset['meta'])
        ->schema_version->toBe(1)
        ->contains_secrets->toBeFalse()
        ->and($dataset['market_candles'])->toHaveCount(219)
        ->and($dataset['news_feature_snapshots'])->not->toBeEmpty()
        ->and($json)->not->toContain('ALPHA_VANTAGE_API_KEY', 'DB_PASSWORD');

    VolatilityForecast::query()->delete();
    FeatureSnapshot::query()->delete();
    SourceRecord::query()->delete();
    MarketCandle::query()->delete();
    NewsFeatureSnapshot::query()->delete();
    SyncRun::query()->delete();

    $this->artisan('oracle:load-demo', ['--path' => $path])
        ->expectsOutputToContain('Demo dataset loaded')
        ->assertSuccessful();

    $countsAfterFirstLoad = [
        SourceRecord::query()->count(),
        MarketCandle::query()->count(),
        NewsFeatureSnapshot::query()->count(),
        VolatilityForecast::query()->count(),
    ];

    $this->artisan('oracle:load-demo', ['--path' => $path])->assertSuccessful();

    expect($countsAfterFirstLoad)
        ->toBe([$exportedRawCount, 219, $exportedNewsCount, VolatilityForecast::query()->count()])
        ->and(SourceRecord::query()->count())->toBe($exportedRawCount)
        ->and(MarketCandle::query()->count())->toBe(219)
        ->and(NewsFeatureSnapshot::query()->count())->toBe($exportedNewsCount)
        ->and(VolatilityForecast::query()->count())->toBeGreaterThan(0)
        ->and(SyncRun::query()->count())->toBe(3);

    File::delete($path);
});

test('the committed real demo dataset builds a working offline dashboard', function () {
    $this->seed(DatabaseSeeder::class);

    $path = base_path('database/fixtures/oracle-demo.json');
    $dataset = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

    $this->artisan('oracle:load-demo', ['--path' => $path])
        ->expectsOutputToContain('Demo dataset loaded')
        ->assertSuccessful();

    expect(SourceRecord::query()->count())
        ->toBe($dataset['meta']['counts']['source_records'])
        ->and(MarketCandle::query()->count())
        ->toBe($dataset['meta']['counts']['market_candles'])
        ->and(NewsFeatureSnapshot::query()->count())
        ->toBe($dataset['meta']['counts']['news_feature_snapshots'])
        ->and(VolatilityForecast::query()->count())->toBeGreaterThan(90)
        ->and(VolatilityForecast::query()->where('status', 'evaluated')->count())->toBeGreaterThan(80)
        ->and(VolatilityForecast::query()->whereNotNull('confidence_pct')->count())->toBeGreaterThanOrEqual(3)
        ->and(SyncRun::query()->count())->toBe(3);

    $this->get(route('home'))->assertOk();
});
