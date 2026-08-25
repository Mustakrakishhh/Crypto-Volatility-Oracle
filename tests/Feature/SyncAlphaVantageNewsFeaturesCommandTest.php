<?php

use App\Enums\SyncRunStatus;
use App\Models\DataSource;
use App\Models\NewsFeatureSnapshot;
use App\Models\SourceRecord;
use App\Models\SyncRun;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.alpha_vantage.api_key' => 'test-key']);
    Http::preventStrayRequests();
    $this->seed(DatabaseSeeder::class);
});

test('it stores raw Alpha Vantage articles and daily weighted sentiment features', function () {
    Http::fake([
        'www.alphavantage.co/*' => Http::response(alphaVantageNewsPayload()),
    ]);

    $this->artisan('oracle:sync-alpha-vantage', [
        '--days' => 30,
        '--end-date' => '2026-08-24',
        '--symbol' => ['ETHUSDT'],
    ])
        ->expectsOutputToContain('2 daily features stored from 3 articles')
        ->assertSuccessful();

    $source = DataSource::query()->where('key', 'alpha-vantage')->sole();
    $syncRun = SyncRun::query()->where('data_source_id', $source->id)->sole();
    $firstSnapshot = NewsFeatureSnapshot::query()
        ->where('data_source_id', $source->id)
        ->oldest('observed_on')
        ->firstOrFail();

    expect($syncRun->status)->toBe(SyncRunStatus::Completed)
        ->and($syncRun->records_received)->toBe(3)
        ->and($syncRun->records_stored)->toBe(2)
        ->and(SourceRecord::query()->where('data_source_id', $source->id)->count())->toBe(3)
        ->and($firstSnapshot->article_count)->toBe(2)
        ->and($firstSnapshot->coverage_share_pct)->toBeNull()
        ->and((float) $firstSnapshot->average_tone)->toBe(-0.3)
        ->and($firstSnapshot->provider_version)->toBe('alpha-vantage-news-sentiment');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['function'] === 'NEWS_SENTIMENT'
        && $request['tickers'] === 'CRYPTO:ETH'
        && $request['time_from'] === '20260725T0000'
        && $request['time_to'] === '20260824T2359'
        && $request['limit'] === 200
        && $request['apikey'] === 'test-key');
});

test('it records an Alpha Vantage API limit response as a failed run', function () {
    Http::fake([
        'www.alphavantage.co/*' => Http::response([
            'Note' => 'Thank you for using Alpha Vantage. Our standard API rate limit is reached.',
        ]),
    ]);

    $this->artisan('oracle:sync-alpha-vantage', ['--symbol' => ['ETHUSDT']])
        ->expectsOutputToContain('Alpha Vantage synchronization failed')
        ->assertFailed();

    $source = DataSource::query()->where('key', 'alpha-vantage')->sole();
    $syncRun = SyncRun::query()->where('data_source_id', $source->id)->sole();

    expect($syncRun->status)->toBe(SyncRunStatus::Failed)
        ->and($syncRun->error_message)->toContain('rate limit');
});

/** @return array<string, mixed> */
function alphaVantageNewsPayload(): array
{
    return [
        'items' => '3',
        'feed' => [
            alphaVantageArticle('first', '20260822T100000', '-0.5', '0.8'),
            alphaVantageArticle('second', '20260822T120000', '0.5', '0.2'),
            alphaVantageArticle('third', '20260823T090000', '0.1', '0.9'),
        ],
    ];
}

/** @return array<string, mixed> */
function alphaVantageArticle(string $id, string $publishedAt, string $sentiment, string $relevance): array
{
    return [
        'title' => "Article {$id}",
        'url' => "https://example.com/{$id}",
        'time_published' => $publishedAt,
        'overall_sentiment_score' => '0.0',
        'ticker_sentiment' => [[
            'ticker' => 'CRYPTO:ETH',
            'relevance_score' => $relevance,
            'ticker_sentiment_score' => $sentiment,
        ]],
    ];
}
