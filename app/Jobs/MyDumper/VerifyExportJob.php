<?php

namespace App\Jobs\MyDumper;

use App\Models\MyDumperExport;
use App\Services\MyDumper\MyDumperExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class VerifyExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $exportId) {}

    public function handle(MyDumperExecutionService $service): void
    {
        $export = MyDumperExport::findOrFail($this->exportId);

        if ($export->status->isFinished()) {
            return;
        }

        try {
            $service->verifyExport($export);
        } catch (Throwable) {
            // Failure persisted in execution service.
        }
    }
}
