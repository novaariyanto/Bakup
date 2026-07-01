<?php

namespace App\Listeners\Backup;

use App\Events\Backup\BackupCompleted;
use App\Events\Backup\BackupFailed;
use App\Services\Notification\BackupNotificationDispatcher;

class SendBackupNotifications
{
    public function __construct(
        private readonly BackupNotificationDispatcher $dispatcher,
    ) {}

    public function handleCompleted(BackupCompleted $event): void
    {
        $this->dispatcher->dispatchSuccess($event->history);
    }

    public function handleFailed(BackupFailed $event): void
    {
        $this->dispatcher->dispatchFailure($event->history);
    }
}
