<?php

namespace App\Console\Commands;

use App\Actions\BuildNewsAdjustedForecasts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('oracle:build-news-model {--symbol=* : Limit the build to one or more asset symbols}')]
#[Description('Build and evaluate the transparent GDELT news-adjusted volatility candidate')]
class BuildNewsAdjustedForecastsCommand extends Command
{
    public function handle(BuildNewsAdjustedForecasts $buildNewsAdjustedForecasts): int
    {
        /** @var list<string> $symbols */
        $symbols = array_values(array_filter(
            (array) $this->option('symbol'),
            fn (mixed $symbol): bool => is_string($symbol) && $symbol !== '',
        ));

        try {
            $summary = $buildNewsAdjustedForecasts->handle($symbols);
        } catch (Throwable $exception) {
            $this->error("News-adjusted model build failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info(
            "News-adjusted model completed: {$summary['forecasts']} forecasts, {$summary['evaluated']} evaluated, {$summary['skipped']} skipped without past news.",
        );

        return self::SUCCESS;
    }
}
