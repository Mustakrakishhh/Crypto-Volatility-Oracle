<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use UnexpectedValueException;

class FetchAlphaVantageNews
{
    /**
     * @return list<array{
     *     provider_key: string,
     *     observed_at: CarbonImmutable,
     *     sentiment: float,
     *     relevance: float,
     *     source_payload: array<string, mixed>
     * }>
     */
    public function handle(
        string $ticker,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
    ): array {
        $apiKey = trim((string) config('services.alpha_vantage.api_key'));

        if ($apiKey === '') {
            throw new InvalidArgumentException('ALPHA_VANTAGE_API_KEY is not configured.');
        }

        $payload = $this->request()
            ->get('/query', [
                'function' => 'NEWS_SENTIMENT',
                'tickers' => $ticker,
                'time_from' => $startAt->copy()->utc()->format('Ymd\THi'),
                'time_to' => $endAt->copy()->utc()->format('Ymd\THi'),
                'sort' => 'LATEST',
                'limit' => (int) config('services.alpha_vantage.news_limit'),
                'apikey' => $apiKey,
            ])
            ->throw()
            ->json();

        if (! is_array($payload)) {
            throw new UnexpectedValueException('Alpha Vantage returned an invalid response.');
        }

        foreach (['Error Message', 'Information', 'Note'] as $errorKey) {
            if (is_string($payload[$errorKey] ?? null)) {
                throw new UnexpectedValueException("Alpha Vantage: {$payload[$errorKey]}");
            }
        }

        if (! is_array($payload['feed'] ?? null)) {
            throw new UnexpectedValueException('Alpha Vantage response does not contain a news feed.');
        }

        $articles = [];

        foreach ($payload['feed'] as $article) {
            if (! is_array($article)) {
                continue;
            }

            $publishedAt = $this->parsePublishedAt($article['time_published'] ?? null);

            if ($publishedAt === null
                || $publishedAt->lessThan($startAt)
                || $publishedAt->greaterThan($endAt)) {
                continue;
            }

            $tickerSentiment = collect(Arr::wrap($article['ticker_sentiment'] ?? []))
                ->first(fn (mixed $item): bool => is_array($item)
                    && ($item['ticker'] ?? null) === $ticker);
            $sentiment = is_array($tickerSentiment)
                ? $tickerSentiment['ticker_sentiment_score'] ?? null
                : $article['overall_sentiment_score'] ?? null;
            $relevance = is_array($tickerSentiment)
                ? $tickerSentiment['relevance_score'] ?? null
                : 1;

            if (! is_numeric($sentiment)) {
                continue;
            }

            $identity = is_string($article['url'] ?? null)
                ? $article['url']
                : implode('|', [
                    is_string($article['title'] ?? null) ? $article['title'] : '',
                    $publishedAt->toIso8601String(),
                ]);
            $articles[] = [
                'provider_key' => hash('sha256', $identity),
                'observed_at' => $publishedAt,
                'sentiment' => (float) $sentiment,
                'relevance' => is_numeric($relevance) ? max(0, (float) $relevance) : 1.0,
                'source_payload' => $article,
            ];
        }

        return $articles;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.alpha_vantage.base_url'))
            ->acceptJson()
            ->withUserAgent('CryptoVolatilityOracle/1.0 research prototype')
            ->connectTimeout((int) config('services.alpha_vantage.connect_timeout'))
            ->timeout((int) config('services.alpha_vantage.timeout'));
    }

    private function parsePublishedAt(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        foreach (['!Ymd\THis', '!Ymd\THi'] as $format) {
            if (! CarbonImmutable::hasFormatWithModifiers($value, $format)) {
                continue;
            }

            $parsed = CarbonImmutable::createFromFormat($format, $value, 'UTC');

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }
}
