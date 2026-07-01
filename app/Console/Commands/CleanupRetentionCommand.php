<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupRetentionService;
use Illuminate\Console\Command;

class CleanupRetentionCommand extends Command
{
    protected $signature = 'backup:cleanup-retention';

    protected $description = 'Apply backup retention policies for all active profiles';

    public function handle(BackupRetentionService $retentionService): int
    {
        $deleted = $retentionService->applyForAllProfiles();

        $this->components->info("Retention cleanup removed {$deleted} backup record(s).");

        return self::SUCCESS;
    }
}
