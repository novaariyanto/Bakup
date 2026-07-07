<?php

namespace App\Services\MyDumper;

use App\Enums\MyDumper\MyDumperExportStatus;
use App\Events\MyDumper\ExportProgressUpdated;
use App\Exceptions\MyDumper\MyDumperException;
use App\Models\MyDumperExport;
use App\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class MyDumperRunner extends BaseService
{
    public function __construct(
        private readonly MyDumperBinaryResolver $binaryResolver,
        private readonly MyDumperCommandBuilder $commandBuilder,
        private readonly MyDumperProgressParser $progressParser,
        private readonly MyDumperLogService $logService,
    ) {}

    public function run(MyDumperExport $export, string $outputDirectory): int
    {
        $binary = $this->binaryResolver->resolve();

        if ($binary === null) {
            throw MyDumperException::notInstalled();
        }

        $command = $this->commandBuilder->buildFromExport($export, $outputDirectory);
        $command[0] = $binary;

        $maskedCommand = $this->commandBuilder->formatCommand(
            $this->commandBuilder->buildFromExport($export, $outputDirectory, maskPassword: true)
        );

        $export->update([
            'command' => $maskedCommand,
            'output_path' => $outputDirectory,
        ]);

        $this->logService->append($export, 'Starting mydumper process.', 'info', 'system');

        $process = new Process($command);
        $process->setTimeout(config('mydumper.job_timeout', 86400));
        $process->start();

        $export->update(['process_pid' => $process->getPid()]);

        $tablesCompleted = 0;

        while ($process->isRunning()) {
            if ($this->isCancelled($export->id)) {
                $process->stop(15);
                throw MyDumperException::cancelled();
            }

            $output = $process->getIncrementalOutput();
            $errorOutput = $process->getIncrementalErrorOutput();

            foreach ($this->splitLines($output) as $line) {
                $this->handleOutputLine($export, $line, 'stdout', $tablesCompleted);
            }

            foreach ($this->splitLines($errorOutput) as $line) {
                $this->handleOutputLine($export, $line, 'stderr', $tablesCompleted);
            }

            usleep(200000);
            $export->refresh();
        }

        $remainingOut = $process->getIncrementalOutput();
        $remainingErr = $process->getIncrementalErrorOutput();

        foreach ($this->splitLines($remainingOut) as $line) {
            $this->handleOutputLine($export, $line, 'stdout', $tablesCompleted);
        }

        foreach ($this->splitLines($remainingErr) as $line) {
            $this->handleOutputLine($export, $line, 'stderr', $tablesCompleted);
        }

        $exitCode = $process->getExitCode() ?? 1;

        if ($exitCode !== 0 && $export->status !== MyDumperExportStatus::Cancelled) {
            throw MyDumperException::processFailed($exitCode, $process->getErrorOutput());
        }

        return $exitCode;
    }

    public function cancel(MyDumperExport $export): void
    {
        Cache::put($this->cancelKey($export->id), true, now()->addHours(6));

        if ($export->process_pid) {
            if (PHP_OS_FAMILY === 'Windows') {
                exec('taskkill /PID '.$export->process_pid.' /F');
            } elseif (function_exists('posix_kill')) {
                posix_kill((int) $export->process_pid, 15);
            }
        }
    }

    public function clearCancelFlag(int $exportId): void
    {
        Cache::forget($this->cancelKey($exportId));
    }

    private function isCancelled(int $exportId): bool
    {
        return (bool) Cache::get($this->cancelKey($exportId), false);
    }

    private function cancelKey(int $exportId): string
    {
        return 'mydumper:export:'.$exportId.':cancel';
    }

    /**
     * @return array<int, string>
     */
    private function splitLines(string $buffer): array
    {
        if ($buffer === '') {
            return [];
        }

        return array_filter(array_map('trim', explode("\n", $buffer)), fn (string $line) => $line !== '');
    }

    private function handleOutputLine(MyDumperExport $export, string $line, string $stream, int &$tablesCompleted): void
    {
        $this->logService->appendStream($export, $line, $stream);

        $parsed = $this->progressParser->parse($line);
        $updates = array_filter($parsed, fn ($value) => $value !== null);

        if ($parsed['tables_completed'] !== null) {
            $tablesCompleted = $parsed['tables_completed'];
        }

        if ($parsed['tables_total'] !== null && $tablesCompleted > 0) {
            $updates['progress_percent'] = $this->progressParser->estimateProgress(
                $tablesCompleted,
                $parsed['tables_total'],
            );
        }

        if ($updates !== []) {
            $export->update($updates);
            event(new ExportProgressUpdated($export->fresh()));
        }
    }
}
