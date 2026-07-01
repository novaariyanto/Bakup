<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class BackupLogger
{
    public function info(string $message, array $context = []): void
    {
        Log::channel('backup')->info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        Log::channel('backup')->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        Log::channel('backup')->error($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        Log::channel('backup')->debug($message, $context);
    }
}
