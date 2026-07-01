<?php

namespace App\Jobs\Backup;

use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Services\Backup\BackupExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExecuteBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $backupProfileId,
        public int $backupHistoryId,
    ) {}

    public function handle(BackupExecutionService $service): void
    {
        $profile = BackupProfile::with([
            'databaseConnection',
            'destinations',
            'excludedTables',
            'includeFolders',
            'excludeFolders',
        ])->findOrFail($this->backupProfileId);

        $history = BackupHistory::findOrFail($this->backupHistoryId);

        try {
            $service->execute($profile, $history);
        } catch (Throwable) {
            // Failure state is persisted inside BackupExecutionService.
        }
    }
}
