<?php

namespace App\Console\Commands;

use App\Actions\BuildVolatilityBaseline;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('oracle:build-baseline {--symbol=* : Limit the build to one or more asset symbols}')]
#[Description('Build and evaluate the 24-hour persistence volatility baseline')]
class BuildVolatilityBaselineCommand extends Command
{
    public function handle(BuildVolatilityBaseline $buildVolatilityBaseline): int
    {
        /** @var list<string> $symbols */
        $symbols = array_values(array_filter(
            (array) $this->option('symbol'),
            fn (mixed $symbol): bool => is_string($symbol) && $symbol !== '',
        ));

        try {
            $summary = $buildVolatilityBaseline->handle($symbols);
        } catch (Throwable $exception) {
            $this->error("Baseline build failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info(
            "Baseline completed: {$summary['forecasts']} forecasts, {$summary['evaluated']} evaluated, {$summary['snapshots']} snapshots.",
        );

        return self::SUCCESS;
    }
}
