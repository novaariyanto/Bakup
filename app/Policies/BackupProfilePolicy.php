<?php

namespace App\Policies;

use App\Models\BackupProfile;
use App\Models\User;

class BackupProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('backup-profiles.manage');
    }

    public function view(User $user, BackupProfile $backupProfile): bool
    {
        return $user->can('backup-profiles.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('backup-profiles.manage');
    }

    public function update(User $user, BackupProfile $backupProfile): bool
    {
        return $user->can('backup-profiles.manage');
    }

    public function delete(User $user, BackupProfile $backupProfile): bool
    {
        return $user->can('backup-profiles.manage');
    }

    public function run(User $user, BackupProfile $backupProfile): bool
    {
        return $user->can('backup-history.run') && $backupProfile->is_active;
    }
}
