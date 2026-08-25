<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

class FetchBybitKlines
{
    /**
     * @return list<array{
     *     opened_at: CarbonImmutable,
     *     closed_at: CarbonImmutable,
     *     open: string,
     *     high: string,
     *     low: string,
     *     close: string,
     *     volume: string,
     *     turnover: string,
     *     is_closed: bool,
     *     source_payload: array<string, string>
     * }>
     */
    public function handle(
        string $symbol,
        string $category = 'linear',
        int $intervalMinutes = 60,
        int $limit = 200,
        ?CarbonInterface $startAt = null,
        ?CarbonInterface $endAt = null,
    ): array {
        $query = [
            'category' => $category,
            'symbol' => $symbol,
            'interval' => (string) $intervalMinutes,
            'limit' => $limit,
        ];

        if ($startAt !== null) {
            $query['start'] = $startAt->getTimestampMs();
        }

        if ($endAt !== null) {
            $query['end'] = $endAt->getTimestampMs();
        }

        $response = $this->request()
            ->get('/v5/market/kline', $query)
            ->throw();

        $payload = $response->json();

        if (! is_array($payload) || ($payload['retCode'] ?? null) !== 0) {
            $message = is_array($payload) && is_string($payload['retMsg'] ?? null)
                ? $payload['retMsg']
                : 'Bybit returned an invalid response.';

            throw new RuntimeException("Bybit request failed: {$message}");
        }

        $rows = data_get($payload, 'result.list');

        if (! is_array($rows)) {
            throw new UnexpectedValueException('Bybit response does not contain a kline list.');
        }

        $candles = [];

        foreach ($rows as $row) {
            if (! is_array($row) || count($row) < 7 || ! $this->containsNumericValues($row)) {
                throw new UnexpectedValueException('Bybit returned a malformed kline row.');
            }

            $openedAt = CarbonImmutable::createFromTimestampMs((int) $row[0], 'UTC');
            $closedAt = $openedAt->addMinutes($intervalMinutes);

            $candles[] = [
                'opened_at' => $openedAt,
                'closed_at' => $closedAt,
                'open' => (string) $row[1],
                'high' => (string) $row[2],
                'low' => (string) $row[3],
                'close' => (string) $row[4],
                'volume' => (string) $row[5],
                'turnover' => (string) $row[6],
                'is_closed' => $closedAt->isPast(),
                'source_payload' => [
                    'category' => $category,
                    'symbol' => $symbol,
                    'interval' => (string) $intervalMinutes,
                    'start_time_ms' => (string) $row[0],
                    'open' => (string) $row[1],
                    'high' => (string) $row[2],
                    'low' => (string) $row[3],
                    'close' => (string) $row[4],
                    'volume' => (string) $row[5],
                    'turnover' => (string) $row[6],
                ],
            ];
        }

        usort(
            $candles,
            fn (array $left, array $right): int => $left['opened_at']->getTimestampMs() <=> $right['opened_at']->getTimestampMs(),
        );

        return $candles;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.bybit.base_url'))
            ->acceptJson()
            ->connectTimeout((int) config('services.bybit.connect_timeout'))
            ->timeout((int) config('services.bybit.timeout'))
            ->retry([100, 500], throw: false);
    }

    /** @param array<int, mixed> $row */
    private function containsNumericValues(array $row): bool
    {
        foreach (array_slice($row, 0, 7) as $value) {
            if (! is_numeric($value)) {
                return false;
            }
        }

        return true;
    }
}
