<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class MyDumperLogger
{
    public function info(string $message, array $context = []): void
    {
        Log::channel('mydumper')->info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        Log::channel('mydumper')->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        Log::channel('mydumper')->error($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        Log::channel('mydumper')->debug($message, $context);
    }
}
