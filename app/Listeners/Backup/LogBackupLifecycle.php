<?php

namespace App\Listeners\Backup;

use App\Events\Backup\BackupCompleted;
use App\Events\Backup\BackupFailed;
use App\Events\Backup\BackupStarted;
use App\Support\BackupLogger;

class LogBackupLifecycle
{
    public function __construct(
        private readonly BackupLogger $logger,
    ) {}

    public function handleStarted(BackupStarted $event): void
    {
        $this->logger->info('Backup started', [
            'history_id' => $event->history->id,
            'profile_id' => $event->history->backup_profile_id,
        ]);
    }

    public function handleCompleted(BackupCompleted $event): void
    {
        $this->logger->info('Backup completed event', [
            'history_id' => $event->history->id,
            'profile_id' => $event->history->backup_profile_id,
        ]);
    }

    public function handleFailed(BackupFailed $event): void
    {
        $this->logger->error('Backup failed event', [
            'history_id' => $event->history->id,
            'profile_id' => $event->history->backup_profile_id,
            'error' => $event->exception->getMessage(),
        ]);
    }
}
