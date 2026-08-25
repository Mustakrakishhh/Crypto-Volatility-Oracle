<?php

namespace App\Console\Commands;

use App\Actions\ExportOracleDemoDataset;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use Throwable;

#[Signature('oracle:export-demo {--days=45 : Number of persisted days to export, from 8 to 90} {--path=database/fixtures/oracle-demo.json : Destination JSON path}')]
#[Description('Export a portable demo JSON from persisted public source data')]
class ExportOracleDemoDatasetCommand extends Command
{
    public function handle(ExportOracleDemoDataset $exportOracleDemoDataset): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

        if ($days === false || $days < 8 || $days > 90) {
            $this->error('The --days option must be an integer between 8 and 90.');

            return self::FAILURE;
        }

        $pathOption = $this->option('path');

        if (! is_string($pathOption) || $pathOption === '') {
            $this->error('The --path option must be a non-empty path.');

            return self::FAILURE;
        }

        try {
            $dataset = $exportOracleDemoDataset->handle($days);
            $json = json_encode(
                $dataset,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
            $path = $this->absolutePath($pathOption);
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $json.PHP_EOL);
        } catch (Throwable $exception) {
            $this->error("Demo dataset export failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Demo dataset exported: %s, %s, SHA-256 %s.',
            $path,
            Number::fileSize(File::size($path)),
            hash_file('sha256', $path),
        ));

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1
            ? $path
            : base_path($path);
    }
}
