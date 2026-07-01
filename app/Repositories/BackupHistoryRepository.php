<?php

namespace App\Repositories;

use App\Enums\BackupHistoryStatus;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BackupHistoryRepository
{
    public function paginate(
        ?string $search = null,
        ?string $status = null,
        ?int $profileId = null,
        int $perPage = 10,
    ): LengthAwarePaginator {
        return BackupHistory::query()
            ->with(['backupProfile', 'triggeredBy'])
            ->when($search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('filename', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhereHas('backupProfile', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($profileId, fn ($query) => $query->where('backup_profile_id', $profileId))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findWithRelations(int $id): ?BackupHistory
    {
        return BackupHistory::with(['backupProfile', 'triggeredBy', 'logs'])->find($id);
    }

    public function create(array $data): BackupHistory
    {
        return BackupHistory::create($data);
    }

    public function update(BackupHistory $history, array $data): BackupHistory
    {
        $history->update($data);

        return $history->fresh();
    }

    public function find(int $id): ?BackupHistory
    {
        return BackupHistory::find($id);
    }

    public function delete(BackupHistory $history): void
    {
        $history->delete();
    }

    public function hasRunningBackup(BackupProfile $profile): bool
    {
        return BackupHistory::query()
            ->where('backup_profile_id', $profile->id)
            ->where('status', BackupHistoryStatus::Running)
            ->exists();
    }

    /**
     * @return Collection<int, BackupHistory>
     */
    public function successfulForProfileOrdered(BackupProfile $profile): Collection
    {
        return BackupHistory::query()
            ->where('backup_profile_id', $profile->id)
            ->where('status', BackupHistoryStatus::Success)
            ->orderByRaw('COALESCE(finished_at, created_at) DESC')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, BackupHistory>
     */
    public function successfulOlderThan(BackupProfile $profile, Carbon $cutoff): Collection
    {
        return BackupHistory::query()
            ->where('backup_profile_id', $profile->id)
            ->where('status', BackupHistoryStatus::Success)
            ->whereRaw('COALESCE(finished_at, created_at) < ?', [$cutoff])
            ->get();
    }
}
