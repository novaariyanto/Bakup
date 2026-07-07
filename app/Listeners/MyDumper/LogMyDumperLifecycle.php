<?php

namespace App\Listeners\MyDumper;

use App\Events\MyDumper\ExportCancelled;
use App\Events\MyDumper\ExportCompleted;
use App\Events\MyDumper\ExportFailed;
use App\Events\MyDumper\ExportStarted;
use App\Support\MyDumperLogger;

class LogMyDumperLifecycle
{
    public function __construct(private readonly MyDumperLogger $logger) {}

    public function handleStarted(ExportStarted $event): void
    {
        $this->logger->info('MyDumper export started', [
            'export_id' => $event->export->id,
            'profile_id' => $event->export->profile_id,
        ]);
    }

    public function handleCompleted(ExportCompleted $event): void
    {
        $this->logger->info('MyDumper export completed', [
            'export_id' => $event->export->id,
            'size' => $event->export->total_size,
        ]);
    }

    public function handleFailed(ExportFailed $event): void
    {
        $this->logger->error('MyDumper export failed', [
            'export_id' => $event->export->id,
            'error' => $event->exception->getMessage(),
        ]);
    }

    public function handleCancelled(ExportCancelled $event): void
    {
        $this->logger->warning('MyDumper export cancelled', [
            'export_id' => $event->export->id,
        ]);
    }
}
