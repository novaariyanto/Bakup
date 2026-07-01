<?php

namespace App\Services\Backup;

use App\Enums\BackupHistoryStatus;
use App\Exceptions\BackupHistoryException;
use App\Models\BackupHistory;
use App\Repositories\BackupHistoryRepository;
use App\Support\BackupLogger;

class BackupHistoryManagementService
{
    public function __construct(
        private readonly BackupHistoryRepository $repository,
        private readonly BackupFileService $fileService,
        private readonly BackupExecutionService $executionService,
        private readonly BackupLogger $logger,
    ) {}

    public function delete(BackupHistory $history): void
    {
        if (in_array($history->status, [BackupHistoryStatus::Pending, BackupHistoryStatus::Running], true)) {
            throw BackupHistoryException::cannotDeleteRunning();
        }

        $this->fileService->deleteFiles($history);
        $this->repository->delete($history);

        $this->logger->info('Backup history deleted', [
            'history_id' => $history->id,
            'profile_id' => $history->backup_profile_id,
        ]);
    }

    public function retry(BackupHistory $history, ?int $userId = null): BackupHistory
    {
        if ($history->status !== BackupHistoryStatus::Failed) {
            throw BackupHistoryException::cannotRetry();
        }

        $history->loadMissing('backupProfile');

        return $this->executionService->dispatch($history->backupProfile, $userId);
    }
}
