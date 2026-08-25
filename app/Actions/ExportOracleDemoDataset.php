<?php

namespace App\Actions;

use App\Models\Asset;
use App\Models\DataSource;
use App\Models\MarketCandle;
use App\Models\NewsFeatureSnapshot;
use App\Models\SourceRecord;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ExportOracleDemoDataset
{
    /**
     * @return array<string, mixed>
     */
    public function handle(int $days = 45): array
    {
        if ($days < 8 || $days > 90) {
            throw new InvalidArgumentException('The demo dataset period must be between 8 and 90 days.');
        }

        $latestCandleAt = MarketCandle::query()
            ->where('is_closed', true)
            ->max('opened_at');

        if (! is_string($latestCandleAt)) {
            throw new InvalidArgumentException('No closed market candles are available for export.');
        }

        $rangeEnd = CarbonImmutable::parse($latestCandleAt, 'UTC')->endOfHour();
        $rangeStart = $rangeEnd->subDays($days)->startOfDay();
        $assetSymbols = Asset::query()->orderBy('display_order')->pluck('symbol', 'id');
        $sourceKeys = DataSource::query()->orderBy('id')->pluck('key', 'id');
        $marketCandles = [];

        foreach (MarketCandle::query()
            ->select([
                'asset_id',
                'data_source_id',
                'interval_minutes',
                'opened_at',
                'closed_at',
                'open',
                'high',
                'low',
                'close',
                'volume',
                'turnover',
                'is_closed',
            ])
            ->whereBetween('opened_at', [$rangeStart, $rangeEnd])
            ->orderBy('asset_id')
            ->orderBy('opened_at')
            ->cursor() as $candle) {
            $marketCandles[] = [
                'asset_symbol' => $assetSymbols->get($candle->asset_id),
                'source_key' => $sourceKeys->get($candle->data_source_id),
                'interval_minutes' => $candle->interval_minutes,
                'opened_at' => $candle->opened_at->utc()->toIso8601String(),
                'closed_at' => $candle->closed_at->utc()->toIso8601String(),
                'open' => $candle->open,
                'high' => $candle->high,
                'low' => $candle->low,
                'close' => $candle->close,
                'volume' => $candle->volume,
                'turnover' => $candle->turnover,
                'is_closed' => $candle->is_closed,
            ];
        }

        $newsFeatures = [];

        foreach (NewsFeatureSnapshot::query()
            ->select([
                'asset_id',
                'data_source_id',
                'observed_on',
                'article_count',
                'monitored_article_count',
                'coverage_share_pct',
                'average_tone',
                'query',
                'provider_version',
            ])
            ->whereBetween('observed_on', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->orderBy('asset_id')
            ->orderBy('data_source_id')
            ->orderBy('observed_on')
            ->cursor() as $snapshot) {
            $newsFeatures[] = [
                'asset_symbol' => $assetSymbols->get($snapshot->asset_id),
                'source_key' => $sourceKeys->get($snapshot->data_source_id),
                'observed_on' => $snapshot->observed_on->toDateString(),
                'article_count' => $snapshot->article_count,
                'monitored_article_count' => $snapshot->monitored_article_count,
                'coverage_share_pct' => $snapshot->coverage_share_pct,
                'average_tone' => $snapshot->average_tone,
                'query' => $snapshot->query,
                'provider_version' => $snapshot->provider_version,
            ];
        }

        $sourceRecords = [];

        foreach (SourceRecord::query()
            ->select([
                'data_source_id',
                'asset_id',
                'record_type',
                'provider_key',
                'observed_at',
                'payload',
                'checksum',
            ])
            ->whereBetween('observed_at', [$rangeStart, $rangeEnd])
            ->orderBy('data_source_id')
            ->orderBy('asset_id')
            ->orderBy('observed_at')
            ->orderBy('provider_key')
            ->cursor() as $record) {
            $sourceRecords[] = [
                'source_key' => $sourceKeys->get($record->data_source_id),
                'asset_symbol' => $record->asset_id === null
                    ? null
                    : $assetSymbols->get($record->asset_id),
                'record_type' => $record->record_type,
                'provider_key' => $record->provider_key,
                'observed_at' => $record->observed_at->utc()->toIso8601String(),
                'payload' => $record->payload,
                'checksum' => $record->checksum,
            ];
        }

        $assets = Asset::query()
            ->select(['symbol', 'name', 'base_currency', 'quote_currency'])
            ->orderBy('display_order')
            ->get()
            ->map(fn (Asset $asset): array => $asset->only([
                'symbol',
                'name',
                'base_currency',
                'quote_currency',
            ]))
            ->all();
        $sources = DataSource::query()
            ->select(['key', 'name', 'type', 'base_url'])
            ->orderBy('id')
            ->get()
            ->map(fn (DataSource $source): array => [
                ...$source->only(['key', 'name', 'base_url']),
                'type' => $source->type->value,
            ])
            ->all();

        return [
            'meta' => [
                'schema_version' => 1,
                'dataset_name' => 'crypto-volatility-oracle-demo',
                'generated_at' => now('UTC')->toIso8601String(),
                'range_start' => $rangeStart->toIso8601String(),
                'range_end' => $rangeEnd->toIso8601String(),
                'period_days' => $days,
                'provenance' => 'Exported from persisted public Bybit, GDELT and Alpha Vantage responses.',
                'contains_secrets' => false,
                'counts' => [
                    'assets' => count($assets),
                    'sources' => count($sources),
                    'source_records' => count($sourceRecords),
                    'market_candles' => count($marketCandles),
                    'news_feature_snapshots' => count($newsFeatures),
                ],
            ],
            'assets' => $assets,
            'sources' => $sources,
            'source_records' => $sourceRecords,
            'market_candles' => $marketCandles,
            'news_feature_snapshots' => $newsFeatures,
        ];
    }
}
