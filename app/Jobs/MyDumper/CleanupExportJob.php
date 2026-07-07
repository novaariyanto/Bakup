<?php

namespace App\Jobs\MyDumper;

use App\Models\MyDumperExport;
use App\Services\MyDumper\MyDumperExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanupExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $exportId) {}

    public function handle(MyDumperExecutionService $service): void
    {
        $export = MyDumperExport::find($this->exportId);

        if ($export === null) {
            return;
        }

        $service->cleanupExport($export);
    }
}
