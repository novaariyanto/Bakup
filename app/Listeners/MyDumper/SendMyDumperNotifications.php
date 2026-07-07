<?php

namespace App\Listeners\MyDumper;

use App\Events\MyDumper\ExportCancelled;
use App\Events\MyDumper\ExportCompleted;
use App\Events\MyDumper\ExportFailed;
use App\Events\MyDumper\ExportUploadCompleted;
use App\Events\MyDumper\ExportVerificationFailed;
use App\Services\Notification\MyDumperNotificationDispatcher;

class SendMyDumperNotifications
{
    public function __construct(private readonly MyDumperNotificationDispatcher $dispatcher) {}

    public function handleCompleted(ExportCompleted $event): void
    {
        $this->dispatcher->dispatchSuccess($event->export);
    }

    public function handleFailed(ExportFailed $event): void
    {
        $this->dispatcher->dispatchFailure($event->export);
    }

    public function handleCancelled(ExportCancelled $event): void
    {
        $this->dispatcher->dispatchFailure($event->export, 'Export dibatalkan.');
    }

    public function handleUploadCompleted(ExportUploadCompleted $event): void
    {
        $this->dispatcher->dispatchUploadCompleted($event->export);
    }

    public function handleVerificationFailed(ExportVerificationFailed $event): void
    {
        $this->dispatcher->dispatchVerificationFailed($event->export, $event->reason);
    }
}
