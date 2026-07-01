<?php

namespace App\Policies;

use App\Enums\BackupHistoryStatus;
use App\Models\BackupHistory;
use App\Models\User;

class BackupHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('backup-history.view');
    }

    public function view(User $user, BackupHistory $backupHistory): bool
    {
        return $user->can('backup-history.view');
    }

    public function delete(User $user, BackupHistory $backupHistory): bool
    {
        return $user->can('backup-history.view')
            && ! in_array($backupHistory->status, [BackupHistoryStatus::Pending, BackupHistoryStatus::Running], true);
    }

    public function download(User $user, BackupHistory $backupHistory): bool
    {
        return $user->can('backup-history.view')
            && $backupHistory->status === BackupHistoryStatus::Success
            && filled($backupHistory->filename);
    }

    public function retry(User $user, BackupHistory $backupHistory): bool
    {
        return $user->can('backup-history.run')
            && $backupHistory->status === BackupHistoryStatus::Failed
            && $backupHistory->backupProfile?->is_active;
    }
}
