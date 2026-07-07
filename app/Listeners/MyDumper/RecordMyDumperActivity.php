<?php

namespace App\Listeners\MyDumper;

use App\Events\MyDumper\ExportCancelled;
use App\Events\MyDumper\ExportCompleted;
use App\Events\MyDumper\ExportFailed;
use App\Events\MyDumper\ExportStarted;
use App\Models\MyDumperExport;

class RecordMyDumperActivity
{
    public function handleStarted(ExportStarted $event): void
    {
        $this->log($event->export, 'Start Export');
    }

    public function handleCompleted(ExportCompleted $event): void
    {
        $this->log($event->export, 'Export Completed');
    }

    public function handleFailed(ExportFailed $event): void
    {
        $this->log($event->export, 'Export Failed');
    }

    public function handleCancelled(ExportCancelled $event): void
    {
        $this->log($event->export, 'Cancel Export');
    }

    private function log(MyDumperExport $export, string $description): void
    {
        $logger = activity()->performedOn($export);

        $causer = $export->creator ?? auth()->user();

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        $logger->withProperties([
            'export_id' => $export->id,
            'status' => $export->status->value,
        ])->log($description);
    }
}
