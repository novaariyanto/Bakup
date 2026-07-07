<?php

namespace App\Console\Commands;

use App\Services\MyDumper\MyDumperScheduleService;
use Illuminate\Console\Command;

class ProcessMyDumperExportsCommand extends Command
{
    protected $signature = 'mydumper:process';

    protected $description = 'Process due MyDumper export profiles';

    public function handle(MyDumperScheduleService $scheduleService): int
    {
        $processed = $scheduleService->processDueProfiles();

        $this->info("Dispatched {$processed} scheduled MyDumper export(s).");

        return self::SUCCESS;
    }
}
