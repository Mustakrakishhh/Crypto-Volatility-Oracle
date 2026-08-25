<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use UnexpectedValueException;

class FetchGdeltTimeline
{
    /**
     * @return list<array{
     *     observed_on: CarbonImmutable,
     *     value: float,
     *     norm: int|null,
     *     source_payload: array<string, mixed>
     * }>
     */
    public function handle(
        string $query,
        string $mode,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
    ): array {
        if (! in_array($mode, ['TimelineVolRaw', 'TimelineTone'], true)) {
            throw new InvalidArgumentException('Unsupported GDELT timeline mode.');
        }

        $payload = $this->request()
            ->get('/api/v2/doc/doc', [
                'query' => $query,
                'mode' => $mode,
                'format' => 'json',
                'STARTDATETIME' => $startAt->copy()->utc()->format('YmdHis'),
                'ENDDATETIME' => $endAt->copy()->utc()->format('YmdHis'),
                'TIMELINESMOOTH' => 0,
            ])
            ->throw()
            ->json();

        if (! is_array($payload) || ! is_array($payload['timeline'] ?? null)) {
            throw new UnexpectedValueException('GDELT response does not contain a timeline.');
        }

        $points = [];

        foreach ($payload['timeline'] as $series) {
            if (! is_array($series) || ! is_array($series['data'] ?? null)) {
                continue;
            }

            foreach ($series['data'] as $point) {
                if (! is_array($point)
                    || ! is_string($point['date'] ?? null)
                    || ! is_numeric($point['value'] ?? null)) {
                    throw new UnexpectedValueException('GDELT returned a malformed timeline point.');
                }

                $points[] = [
                    'observed_on' => $this->parseDate($point['date']),
                    'value' => (float) $point['value'],
                    'norm' => is_numeric($point['norm'] ?? null) ? (int) $point['norm'] : null,
                    'source_payload' => $point,
                ];
            }
        }

        usort(
            $points,
            fn (array $left, array $right): int => $left['observed_on']->getTimestamp() <=> $right['observed_on']->getTimestamp(),
        );

        return $points;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.gdelt.base_url'))
            ->acceptJson()
            ->withUserAgent('CryptoVolatilityOracle/1.0 research prototype')
            ->connectTimeout((int) config('services.gdelt.connect_timeout'))
            ->timeout((int) config('services.gdelt.timeout'))
            ->retry([12000, 24000, 48000], throw: false);
    }

    private function parseDate(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Ymd\THis\Z', $date, 'UTC');

        if ($parsed === null) {
            throw new UnexpectedValueException('GDELT returned an invalid timeline date.');
        }

        return $parsed->startOfDay();
    }
}
