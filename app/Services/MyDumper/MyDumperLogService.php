<?php

namespace App\Services\MyDumper;

use App\Models\MyDumperExport;
use App\Models\MyDumperExportLog;
use App\Services\BaseService;
use App\Support\MyDumperLogger;
use Illuminate\Support\Facades\File;

class MyDumperLogService extends BaseService
{
    public function __construct(
        private readonly MyDumperLogger $logger,
    ) {}

    public function initialize(MyDumperExport $export): string
    {
        $directory = storage_path('logs/mydumper');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.$export->uuid.'.log';
        File::put($path, '');

        $export->update(['log_path' => $path]);

        return $path;
    }

    public function append(
        MyDumperExport $export,
        string $message,
        string $level = 'info',
        string $stream = 'system',
    ): void {
        MyDumperExportLog::create([
            'export_id' => $export->id,
            'level' => $level,
            'stream' => $stream,
            'message' => $message,
            'created_at' => now(),
        ]);

        if ($export->log_path) {
            File::append($export->log_path, '['.now()->toDateTimeString()."] [{$level}] [{$stream}] {$message}".PHP_EOL);
        }

        match ($level) {
            'error' => $this->logger->error($message, ['export_id' => $export->id]),
            'warning' => $this->logger->warning($message, ['export_id' => $export->id]),
            default => $this->logger->info($message, ['export_id' => $export->id]),
        };
    }

    public function appendStream(MyDumperExport $export, string $line, string $stream): void
    {
        $level = str_contains(strtolower($line), 'error') ? 'error' : 'info';
        $this->append($export, $line, $level, $stream);
    }

    public function read(MyDumperExport $export, ?string $search = null): string
    {
        if (! $export->log_path || ! File::exists($export->log_path)) {
            return '';
        }

        $content = File::get($export->log_path);

        if ($search === null || $search === '') {
            return $content;
        }

        $lines = collect(explode(PHP_EOL, $content))
            ->filter(fn (string $line) => stripos($line, $search) !== false)
            ->values()
            ->all();

        return implode(PHP_EOL, $lines);
    }
}
