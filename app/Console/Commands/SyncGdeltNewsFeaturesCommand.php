<?php

namespace App\Console\Commands;

use App\Actions\SyncGdeltNewsFeatures;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('oracle:sync-gdelt {--days=90 : Number of daily intervals, from 8 to 365} {--end-date= : Inclusive range end in YYYY-MM-DD format (UTC)} {--symbol=* : Limit the sync to one or more asset symbols}')]
#[Description('Synchronize daily news coverage and tone features from GDELT DOC 2.0')]
class SyncGdeltNewsFeaturesCommand extends Command
{
    public function handle(SyncGdeltNewsFeatures $syncGdeltNewsFeatures): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

        if ($days === false || $days < 8 || $days > 365) {
            $this->error('The --days option must be an integer between 8 and 365.');

            return self::FAILURE;
        }

        $endDateOption = $this->option('end-date');

        if ($endDateOption !== null
            && ! CarbonImmutable::hasFormat($endDateOption, 'Y-m-d')) {
            $this->error('The --end-date option must use the YYYY-MM-DD format.');

            return self::FAILURE;
        }

        $rangeEnd = is_string($endDateOption)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $endDateOption, 'UTC')
            : null;

        /** @var list<string> $symbols */
        $symbols = array_values(array_filter(
            (array) $this->option('symbol'),
            fn (mixed $symbol): bool => is_string($symbol) && $symbol !== '',
        ));

        try {
            $syncRun = $syncGdeltNewsFeatures->handle($days, $symbols, $rangeEnd);
        } catch (Throwable $exception) {
            $this->error("GDELT synchronization failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info(
            "GDELT synchronization completed: {$syncRun->records_stored} daily features stored from {$syncRun->records_received} timeline points.",
        );

        return self::SUCCESS;
    }
}
