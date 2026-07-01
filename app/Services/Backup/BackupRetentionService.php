<?php

namespace App\Services\Backup;

use App\Enums\BackupHistoryStatus;
use App\Enums\RetentionType;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Repositories\BackupHistoryRepository;
use App\Support\BackupLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BackupRetentionService
{
    public function __construct(
        private readonly BackupHistoryRepository $historyRepository,
        private readonly BackupFileService $fileService,
        private readonly BackupLogger $logger,
    ) {}

    public function applyForProfile(BackupProfile $profile): int
    {
        $histories = match ($profile->retention_type) {
            RetentionType::KeepLast => $this->resolveKeepLastExcess($profile),
            RetentionType::DeleteOlderThanDays => $this->resolveExpiredHistories($profile),
        };

        return $this->deleteHistories($profile, $histories);
    }

    public function applyForAllProfiles(): int
    {
        $deleted = 0;

        BackupProfile::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (BackupProfile $profile) use (&$deleted): void {
                $deleted += $this->applyForProfile($profile);
            });

        return $deleted;
    }

    /**
     * @return Collection<int, \App\Models\BackupHistory>
     */
    private function resolveKeepLastExcess(BackupProfile $profile): Collection
    {
        $keepCount = max(1, $profile->retention_value);

        return $this->historyRepository
            ->successfulForProfileOrdered($profile)
            ->slice($keepCount)
            ->values();
    }

    /**
     * @return Collection<int, \App\Models\BackupHistory>
     */
    private function resolveExpiredHistories(BackupProfile $profile): Collection
    {
        $cutoff = Carbon::now()->subDays(max(1, $profile->retention_value));

        return $this->historyRepository->successfulOlderThan($profile, $cutoff);
    }

    /**
     * @param  Collection<int, \App\Models\BackupHistory>  $histories
     */
    private function deleteHistories(BackupProfile $profile, Collection $histories): int
    {
        $deleted = 0;

        foreach ($histories as $history) {
            $this->purgeHistory($history);
            $deleted++;
        }

        if ($deleted > 0) {
            $this->logger->info('Retention policy applied', [
                'profile_id' => $profile->id,
                'retention_type' => $profile->retention_type->value,
                'retention_value' => $profile->retention_value,
                'deleted_count' => $deleted,
            ]);
        }

        return $deleted;
    }

    private function purgeHistory(BackupHistory $history): void
    {
        if (in_array($history->status, [BackupHistoryStatus::Pending, BackupHistoryStatus::Running], true)) {
            return;
        }

        $this->fileService->deleteFiles($history);
        $this->historyRepository->delete($history);

        $this->logger->info('Backup history purged by retention', [
            'history_id' => $history->id,
            'profile_id' => $history->backup_profile_id,
        ]);
    }
}
