<?php

namespace App\Console\Commands;

use App\Actions\SyncAlphaVantageNewsFeatures;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('oracle:sync-alpha-vantage {--days=30 : Number of daily intervals, from 1 to 365} {--end-date= : Inclusive range end in YYYY-MM-DD format (UTC)} {--symbol=* : Limit the sync to one or more asset symbols}')]
#[Description('Synchronize daily crypto news and sentiment features from Alpha Vantage')]
class SyncAlphaVantageNewsFeaturesCommand extends Command
{
    public function handle(SyncAlphaVantageNewsFeatures $syncAlphaVantageNewsFeatures): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

        if ($days === false || $days < 1 || $days > 365) {
            $this->error('The --days option must be an integer between 1 and 365.');

            return self::FAILURE;
        }

        $rangeEnd = $this->rangeEnd();

        if ($rangeEnd === false) {
            return self::FAILURE;
        }

        /** @var list<string> $symbols */
        $symbols = array_values(array_filter(
            (array) $this->option('symbol'),
            fn (mixed $symbol): bool => is_string($symbol) && $symbol !== '',
        ));

        try {
            $syncRun = $syncAlphaVantageNewsFeatures->handle($days, $symbols, $rangeEnd);
        } catch (Throwable $exception) {
            $this->error("Alpha Vantage synchronization failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info(
            "Alpha Vantage synchronization completed: {$syncRun->records_stored} daily features stored from {$syncRun->records_received} articles.",
        );

        return self::SUCCESS;
    }

    private function rangeEnd(): CarbonImmutable|false|null
    {
        $endDateOption = $this->option('end-date');

        if ($endDateOption === null) {
            return null;
        }

        if (! CarbonImmutable::hasFormat($endDateOption, 'Y-m-d')) {
            $this->error('The --end-date option must use the YYYY-MM-DD format.');

            return false;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $endDateOption, 'UTC');
    }
}
