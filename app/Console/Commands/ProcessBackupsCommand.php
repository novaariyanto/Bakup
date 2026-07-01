<?php

namespace App\Console\Commands;

use App\Services\Schedule\ScheduleService;
use Illuminate\Console\Command;

class ProcessBackupsCommand extends Command
{
    protected $signature = 'backup:process';

    protected $description = 'Process backup profiles that are due to run';

    public function handle(ScheduleService $scheduleService): int
    {
        $processed = $scheduleService->processDueProfiles();

        $this->components->info("Processed {$processed} backup profile(s).");

        return self::SUCCESS;
    }
}
