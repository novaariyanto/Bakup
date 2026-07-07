<?php

namespace App\Jobs\MyDumper;

use App\Events\MyDumper\ExportUploadCompleted;
use App\Models\MyDumperExport;
use App\Services\MyDumper\MyDumperExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class UploadExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(public int $exportId) {}

    public function handle(MyDumperExecutionService $service): void
    {
        $export = MyDumperExport::findOrFail($this->exportId);

        if ($export->status->isFinished()) {
            return;
        }

        try {
            $service->uploadExport($export);
            event(new ExportUploadCompleted($export->fresh()));
        } catch (Throwable) {
            // Failure persisted in execution service.
        }
    }
}
