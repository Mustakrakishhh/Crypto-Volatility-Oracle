<?php

use App\Enums\SyncRunStatus;
use App\Models\NewsFeatureSnapshot;
use App\Models\SourceRecord;
use App\Models\SyncRun;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    $this->seed(DatabaseSeeder::class);
});

test('it stores aligned daily GDELT volume and tone features for every asset', function () {
    Http::fake(function (Request $request) {
        return Http::response(gdeltTimelinePayload(
            $request['mode'] === 'TimelineVolRaw' ? [120, 180] : [-1.5, -3.25],
            $request['mode'] === 'TimelineVolRaw',
        ));
    });

    $this->artisan('oracle:sync-gdelt', ['--days' => 90])
        ->expectsOutputToContain('6 daily features stored from 12 timeline points')
        ->assertSuccessful();

    expect(NewsFeatureSnapshot::query()->count())->toBe(6)
        ->and(SourceRecord::query()->count())->toBe(12);

    $snapshot = NewsFeatureSnapshot::query()->oldest('observed_on')->firstOrFail();
    $syncRun = SyncRun::query()->sole();

    expect($snapshot->article_count)->toBe(120)
        ->and((float) $snapshot->coverage_share_pct)->toBe(0.1)
        ->and((float) $snapshot->average_tone)->toBe(-1.5)
        ->and($syncRun->status)->toBe(SyncRunStatus::Completed)
        ->and($syncRun->records_received)->toBe(12)
        ->and($syncRun->records_stored)->toBe(6)
        ->and(SourceRecord::query()->where('record_type', 'news_timeline_volume')->firstOrFail()->payload)
        ->toHaveKeys(['query', 'mode', 'point']);

    Http::assertSentCount(6);
    Http::assertSent(fn (Request $request): bool => str_starts_with(
        $request->url(),
        'https://api.gdeltproject.org/api/v2/doc/doc?',
    ) && in_array($request['mode'], ['TimelineVolRaw', 'TimelineTone'], true)
        && $request['format'] === 'json'
        && $request['TIMELINESMOOTH'] === 0);
});

test('it records a failed run when GDELT rate limits the request', function () {
    Http::fake([
        'api.gdeltproject.org/*' => Http::response(['message' => 'Too Many Requests'], 429),
    ]);

    $this->artisan('oracle:sync-gdelt', ['--days' => 90])
        ->expectsOutputToContain('GDELT synchronization failed')
        ->assertFailed();

    $syncRun = SyncRun::query()->sole();

    expect($syncRun->status)->toBe(SyncRunStatus::Failed)
        ->and($syncRun->error_message)->toContain('429')
        ->and(NewsFeatureSnapshot::query()->count())->toBe(0)
        ->and(SourceRecord::query()->count())->toBe(0);
});

test('it can synchronize a historical date window', function () {
    Http::fake(fn (Request $request) => Http::response(gdeltTimelinePayload(
        $request['mode'] === 'TimelineVolRaw' ? [120, 180] : [-1.5, -3.25],
        $request['mode'] === 'TimelineVolRaw',
    )));

    $this->artisan('oracle:sync-gdelt', [
        '--days' => 90,
        '--end-date' => '2026-05-26',
        '--symbol' => ['ETHUSDT'],
    ])->assertSuccessful();

    $syncRun = SyncRun::query()->sole();

    expect($syncRun->context)
        ->range_start->toBe('2026-02-25T00:00:00+00:00')
        ->range_end->toBe('2026-05-26T00:00:00+00:00')
        ->symbols->toBe(['ETHUSDT']);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request['STARTDATETIME'] === '20260225000000'
        && $request['ENDDATETIME'] === '20260526000000');
});

test('it rejects an invalid historical end date', function () {
    $this->artisan('oracle:sync-gdelt', [
        '--end-date' => '26.05.2026',
    ])
        ->expectsOutput('The --end-date option must use the YYYY-MM-DD format.')
        ->assertFailed();

    expect(SyncRun::query()->count())->toBe(0);
    Http::assertNothingSent();
});

/**
 * @param  list<int|float>  $values
 * @return array<string, mixed>
 */
function gdeltTimelinePayload(array $values, bool $withNorm): array
{
    $dates = ['20260822T000000Z', '20260823T000000Z'];

    return [
        'query_details' => ['date_resolution' => 'day'],
        'timeline' => [[
            'series' => $withNorm ? 'Article Count' : 'Average Tone',
            'data' => array_map(
                fn (string $date, int|float $value): array => array_filter([
                    'date' => $date,
                    'value' => $value,
                    'norm' => $withNorm ? 120000 : null,
                ], fn (mixed $item): bool => $item !== null),
                $dates,
                $values,
            ),
        ]],
    ];
}
