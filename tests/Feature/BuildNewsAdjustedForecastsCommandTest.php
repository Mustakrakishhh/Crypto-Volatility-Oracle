<?php

use App\Actions\BuildNewsAdjustedForecasts;
use App\Actions\BuildVolatilityBaseline;
use App\Models\Asset;
use App\Models\DataSource;
use App\Models\FeatureSnapshot;
use App\Models\NewsFeatureSnapshot;
use App\Models\VolatilityForecast;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('it builds an idempotent past-only news candidate beside the baseline', function () {
    $this->seed(DatabaseSeeder::class);

    $asset = Asset::query()->where('symbol', 'BTCUSDT')->sole();
    $bybit = DataSource::query()->where('key', 'bybit')->sole();
    $gdelt = DataSource::query()->where('key', 'gdelt')->sole();
    $alphaVantage = DataSource::query()->where('key', 'alpha-vantage')->sole();
    $startsAt = now('UTC')->startOfDay()->subDays(20)->addHours(12);

    for ($day = 0; $day < 10; $day++) {
        $predictedAt = $startsAt->copy()->addDays($day);
        $snapshot = FeatureSnapshot::factory()->create([
            'asset_id' => $asset->id,
            'data_source_id' => $bybit->id,
            'calculated_at' => $predictedAt,
        ]);
        VolatilityForecast::factory()->create([
            'asset_id' => $asset->id,
            'feature_snapshot_id' => $snapshot->id,
            'predicted_at' => $predictedAt,
            'target_at' => $predictedAt->copy()->addDay(),
            'predicted_volatility_pct' => '2.000000',
            'actual_volatility_pct' => '2.500000',
            'absolute_error' => '0.500000',
            'squared_error' => '0.25000000',
            'status' => 'evaluated',
            'evaluated_at' => $predictedAt->copy()->addDay(),
            'model_version' => BuildVolatilityBaseline::MODEL_VERSION,
        ]);
    }

    for ($day = -9; $day < 10; $day++) {
        NewsFeatureSnapshot::factory()->create([
            'asset_id' => $asset->id,
            'data_source_id' => $gdelt->id,
            'observed_on' => $startsAt->copy()->addDays($day)->startOfDay(),
            'article_count' => 100,
            'monitored_article_count' => 100000,
            'coverage_share_pct' => '0.10000000',
            'average_tone' => '-2.000000',
        ]);
        NewsFeatureSnapshot::factory()->create([
            'asset_id' => $asset->id,
            'data_source_id' => $alphaVantage->id,
            'observed_on' => $startsAt->copy()->addDays($day)->startOfDay(),
            'article_count' => 10,
            'monitored_article_count' => null,
            'coverage_share_pct' => null,
            'average_tone' => '-0.500000',
            'query' => 'CRYPTO:BTC',
            'provider_version' => 'alpha-vantage-news-sentiment',
        ]);
    }

    $summary = app(BuildNewsAdjustedForecasts::class)->handle(['BTCUSDT']);

    expect($summary)->toMatchArray(['forecasts' => 10, 'evaluated' => 10, 'skipped' => 0]);

    $candidates = VolatilityForecast::query()
        ->where('model_version', BuildNewsAdjustedForecasts::MODEL_VERSION)
        ->oldest('predicted_at')
        ->get();
    $firstCandidate = $candidates->firstOrFail();

    expect($candidates)->toHaveCount(10)
        ->and((float) $firstCandidate->predicted_volatility_pct)->toBe(2.04)
        ->and($firstCandidate->model_inputs['lag_days'])->toBe(1)
        ->and($firstCandidate->model_inputs['source'])->toBe('gdelt-doc-2')
        ->and($firstCandidate->model_inputs['feature_date'])->toBe(
            $firstCandidate->predicted_at->copy()->subDay()->toDateString(),
        );

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('assets.0.model_comparison.status', 'ready')
        ->where('assets.0.model_comparison.verdict', 'insufficient_data')
        ->where('assets.0.model_comparison.candidate.sample_size', 10)
        ->where('assets.0.model_comparison.candidate.news_inputs.lag_days', 1));

    NewsFeatureSnapshot::query()->where('data_source_id', $gdelt->id)->delete();
    app(BuildNewsAdjustedForecasts::class)->handle(['BTCUSDT']);

    expect($firstCandidate->refresh()->model_inputs['source'])
        ->toBe('alpha-vantage-news-sentiment')
        ->and($firstCandidate->model_inputs['attention_metric'])->toBe('article_count');

    $predictionBeforeFutureNews = $firstCandidate->predicted_volatility_pct;

    NewsFeatureSnapshot::query()->updateOrCreate(
        [
            'asset_id' => $asset->id,
            'data_source_id' => $gdelt->id,
            'observed_on' => $firstCandidate->predicted_at->copy()->startOfDay(),
        ],
        [
            'article_count' => 50000,
            'monitored_article_count' => 100000,
            'coverage_share_pct' => '50.00000000',
            'average_tone' => '-10.000000',
            'query' => '(Bitcoin OR BTC)',
            'provider_version' => 'gdelt-doc-2',
        ],
    );

    $this->artisan('oracle:build-news-model', ['--symbol' => ['BTCUSDT']])->assertSuccessful();

    expect($firstCandidate->refresh()->predicted_volatility_pct)->toBe($predictionBeforeFutureNews)
        ->and(VolatilityForecast::query()
            ->where('model_version', BuildNewsAdjustedForecasts::MODEL_VERSION)
            ->count())->toBe(10);
});
