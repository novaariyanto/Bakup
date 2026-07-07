<?php

namespace App\Jobs\MyDumper;

use App\Models\MyDumperExport;
use App\Services\MyDumper\MyDumperExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunMyDumperExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $exportId)
    {
        $this->timeout = (int) config('mydumper.job_timeout', 86400);
    }

    public function handle(MyDumperExecutionService $service): void
    {
        $export = MyDumperExport::findOrFail($this->exportId);

        try {
            $service->runExport($export);
        } catch (Throwable) {
            // Failure persisted in execution service.
        }
    }
}
