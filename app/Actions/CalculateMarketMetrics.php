<?php

namespace App\Actions;

use App\Models\MarketCandle;
use Illuminate\Database\Eloquent\Collection;

class CalculateMarketMetrics
{
    /**
     * @param  Collection<int, MarketCandle>  $candles
     * @return array{
     *     change_24h: float|null,
     *     volatility_24h: float|null,
     *     data_completeness: int,
     *     observed_returns: int,
     *     expected_returns: int,
     *     window_started_at: string|null,
     *     window_ended_at: string|null,
     *     risk_score: int|null,
     *     risk_level: string|null,
     *     risk_label: string|null,
     *     factors: list<array{
     *         key: string,
     *         label: string,
     *         value: float,
     *         unit: string,
     *         contribution: int,
     *         max_contribution: int,
     *         explanation: string
     *     }>
     * }
     */
    public function handle(Collection $candles, int $intervalMinutes = 60, int $horizonHours = 24): array
    {
        $expectedReturns = (int) (($horizonHours * 60) / $intervalMinutes);
        $orderedCandles = $candles
            ->filter(fn (MarketCandle $candle): bool => $candle->is_closed
                && $candle->interval_minutes === $intervalMinutes
                && (float) $candle->close > 0)
            ->sortBy('opened_at')
            ->unique(fn (MarketCandle $candle): int => $candle->opened_at->getTimestamp())
            ->values();

        $latestCandle = $orderedCandles->last();

        if (! $latestCandle instanceof MarketCandle) {
            return $this->emptyMetrics($expectedReturns);
        }

        $windowStartsAt = $latestCandle->opened_at->copy()->subHours($horizonHours);
        $windowCandles = $orderedCandles
            ->filter(fn (MarketCandle $candle): bool => $candle->opened_at->betweenIncluded(
                $windowStartsAt,
                $latestCandle->opened_at,
            ))
            ->values();

        $logReturns = [];

        for ($index = 1; $index < $windowCandles->count(); $index++) {
            $previousCandle = $windowCandles->get($index - 1);
            $currentCandle = $windowCandles->get($index);

            if (! $previousCandle instanceof MarketCandle || ! $currentCandle instanceof MarketCandle) {
                continue;
            }

            $elapsedSeconds = $currentCandle->opened_at->getTimestamp() - $previousCandle->opened_at->getTimestamp();

            if ($elapsedSeconds !== $intervalMinutes * 60) {
                continue;
            }

            $logReturns[] = log((float) $currentCandle->close / (float) $previousCandle->close);
        }

        $observedReturns = count($logReturns);
        $dataCompleteness = min(100, (int) round(($observedReturns / $expectedReturns) * 100));
        $baseMetrics = [
            'data_completeness' => $dataCompleteness,
            'observed_returns' => $observedReturns,
            'expected_returns' => $expectedReturns,
            'window_started_at' => $windowCandles->first()?->opened_at->toIso8601String(),
            'window_ended_at' => $latestCandle->opened_at->toIso8601String(),
        ];

        if ($observedReturns !== $expectedReturns) {
            return [
                'change_24h' => null,
                'volatility_24h' => null,
                ...$baseMetrics,
                'risk_score' => null,
                'risk_level' => null,
                'risk_label' => null,
                'factors' => [],
            ];
        }

        $firstCandle = $windowCandles->first();

        if (! $firstCandle instanceof MarketCandle) {
            return $this->emptyMetrics($expectedReturns);
        }

        $change24h = (((float) $latestCandle->close / (float) $firstCandle->close) - 1) * 100;
        $volatility24h = sqrt(array_sum(array_map(
            fn (float $return): float => $return ** 2,
            $logReturns,
        ))) * 100;
        $volatilityContribution = min(70, (int) round(($volatility24h / 8) * 70));
        $priceMoveContribution = min(30, (int) round((abs($change24h) / 12) * 30));
        $riskScore = min(100, $volatilityContribution + $priceMoveContribution);
        [$riskLevel, $riskLabel] = $this->riskBand($riskScore);

        return [
            'change_24h' => round($change24h, 2),
            'volatility_24h' => round($volatility24h, 2),
            ...$baseMetrics,
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'risk_label' => $riskLabel,
            'factors' => [
                [
                    'key' => 'realized_volatility',
                    'label' => 'Реализованная волатильность',
                    'value' => round($volatility24h, 2),
                    'unit' => '%',
                    'contribution' => $volatilityContribution,
                    'max_contribution' => 70,
                    'explanation' => 'Корень из суммы квадратов часовых лог-доходностей; 8% RV дают максимум 70 баллов.',
                ],
                [
                    'key' => 'absolute_price_change',
                    'label' => 'Абсолютное изменение цены',
                    'value' => round(abs($change24h), 2),
                    'unit' => '%',
                    'contribution' => $priceMoveContribution,
                    'max_contribution' => 30,
                    'explanation' => 'Модуль изменения цены между началом и концом окна; движение 12% даёт максимум 30 баллов.',
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     change_24h: null,
     *     volatility_24h: null,
     *     data_completeness: int,
     *     observed_returns: int,
     *     expected_returns: int,
     *     window_started_at: null,
     *     window_ended_at: null,
     *     risk_score: null,
     *     risk_level: null,
     *     risk_label: null,
     *     factors: array{}
     * }
     */
    private function emptyMetrics(int $expectedReturns): array
    {
        return [
            'change_24h' => null,
            'volatility_24h' => null,
            'data_completeness' => 0,
            'observed_returns' => 0,
            'expected_returns' => $expectedReturns,
            'window_started_at' => null,
            'window_ended_at' => null,
            'risk_score' => null,
            'risk_level' => null,
            'risk_label' => null,
            'factors' => [],
        ];
    }

    /** @return array{string, string} */
    private function riskBand(int $riskScore): array
    {
        return match (true) {
            $riskScore <= 25 => ['low', 'Низкий'],
            $riskScore <= 50 => ['moderate', 'Умеренный'],
            $riskScore <= 75 => ['high', 'Высокий'],
            default => ['extreme', 'Экстремальный'],
        };
    }
}
