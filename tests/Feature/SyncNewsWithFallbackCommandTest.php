<?php

use App\Enums\SyncRunStatus;
use App\Models\DataSource;
use App\Models\SyncRun;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

test('it switches to Alpha Vantage when GDELT fails', function () {
    config(['services.alpha_vantage.api_key' => 'test-key']);
    Http::preventStrayRequests();
    Sleep::fake();
    $this->seed(DatabaseSeeder::class);
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'api.gdeltproject.org')) {
            return Http::response(['message' => 'Too Many Requests'], 429);
        }

        return Http::response([
            'items' => '1',
            'feed' => [[
                'title' => 'Ethereum article',
                'url' => 'https://example.com/ethereum',
                'time_published' => '20260823T090000',
                'overall_sentiment_score' => '0.1',
                'ticker_sentiment' => [[
                    'ticker' => 'CRYPTO:ETH',
                    'relevance_score' => '0.9',
                    'ticker_sentiment_score' => '0.1',
                ]],
            ]],
        ]);
    });

    $this->artisan('oracle:sync-news', [
        '--days' => 30,
        '--end-date' => '2026-08-24',
        '--symbol' => ['ETHUSDT'],
    ])
        ->expectsOutputToContain('Switching to Alpha Vantage fallback')
        ->expectsOutputToContain('Alpha Vantage fallback completed')
        ->assertSuccessful();

    $gdelt = DataSource::query()->where('key', 'gdelt')->sole();
    $alphaVantage = DataSource::query()->where('key', 'alpha-vantage')->sole();

    expect(SyncRun::query()->where('data_source_id', $gdelt->id)->sole()->status)
        ->toBe(SyncRunStatus::Failed)
        ->and(SyncRun::query()->where('data_source_id', $alphaVantage->id)->sole()->status)
        ->toBe(SyncRunStatus::Completed);
});
