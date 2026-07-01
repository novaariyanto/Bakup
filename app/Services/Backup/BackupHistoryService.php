<?php

namespace App\Services\Backup;

use App\Enums\BackupHistoryStatus;
use App\Enums\BackupStage;
use App\Models\BackupHistory;
use App\Models\BackupLog;
use App\Models\BackupProfile;
use App\Repositories\BackupHistoryRepository;
use App\Services\BaseService;
use App\Support\BackupLogger;
use Illuminate\Support\Facades\Cache;

class BackupHistoryService extends BaseService
{
    public function __construct(
        private readonly BackupHistoryRepository $repository,
        private readonly BackupLogger $logger,
    ) {}

    public function createPending(BackupProfile $profile, ?int $userId = null): BackupHistory
    {
        $history = $this->repository->create([
            'backup_profile_id' => $profile->id,
            'triggered_by' => $userId,
            'status' => BackupHistoryStatus::Pending,
            'metadata' => [
                'profile_name' => $profile->name,
                'profile_uuid' => $profile->uuid,
            ],
        ]);

        $this->logger->info('Backup history created', [
            'history_id' => $history->id,
            'profile_id' => $profile->id,
        ]);

        return $history;
    }

    public function markRunning(BackupHistory $history): BackupHistory
    {
        return $this->repository->update($history, [
            'status' => BackupHistoryStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function markStage(BackupHistory $history, BackupStage $stage, ?string $message = null): BackupHistory
    {
        $this->repository->update($history, [
            'current_stage' => $stage->value,
        ]);

        Cache::put(
            $this->stageCacheKey($history->id),
            $stage->value,
            now()->addHours(6),
        );

        $this->addLog($history, $stage, $message ?? $stage->label());

        return $history->fresh();
    }

    public function markSuccess(
        BackupHistory $history,
        ?string $filename = null,
        ?int $originalSizeBytes = null,
        ?int $compressedSizeBytes = null,
        array $metadata = [],
    ): BackupHistory {
        $finishedAt = now();
        $startedAt = $history->started_at ?? $finishedAt;

        $updated = $this->repository->update($history, [
            'status' => BackupHistoryStatus::Success,
            'current_stage' => BackupStage::Finished->value,
            'filename' => $filename,
            'original_size_bytes' => $originalSizeBytes,
            'compressed_size_bytes' => $compressedSizeBytes,
            'duration_seconds' => (int) $startedAt->diffInSeconds($finishedAt),
            'finished_at' => $finishedAt,
            'message' => 'Backup completed successfully.',
            'metadata' => array_merge($history->metadata ?? [], $metadata),
        ]);

        $this->markStage($updated, BackupStage::Finished, 'Backup completed successfully.');
        Cache::forget($this->stageCacheKey($history->id));

        $this->logger->info('Backup completed successfully', [
            'history_id' => $history->id,
            'filename' => $filename,
        ]);

        return $updated->fresh();
    }

    public function markFailed(BackupHistory $history, string $message, ?string $technicalMessage = null): BackupHistory
    {
        $finishedAt = now();
        $startedAt = $history->started_at ?? $finishedAt;

        $updated = $this->repository->update($history, [
            'status' => BackupHistoryStatus::Failed,
            'finished_at' => $finishedAt,
            'duration_seconds' => (int) $startedAt->diffInSeconds($finishedAt),
            'message' => $message,
        ]);

        $this->addLog(
            history: $history,
            stage: BackupStage::Finished,
            message: $technicalMessage ?? $message,
            level: 'error',
        );
        Cache::forget($this->stageCacheKey($history->id));

        $this->logger->error('Backup failed', [
            'history_id' => $history->id,
            'message' => $message,
        ]);

        return $updated->fresh();
    }

    public function addLog(
        BackupHistory $history,
        BackupStage $stage,
        string $message,
        string $level = 'info',
    ): BackupLog {
        return BackupLog::create([
            'backup_history_id' => $history->id,
            'stage' => $stage->value,
            'level' => $level,
            'message' => $message,
        ]);
    }

    private function stageCacheKey(int $historyId): string
    {
        return "backup:{$historyId}:stage";
    }
}
