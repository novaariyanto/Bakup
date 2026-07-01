<?php

namespace App\Policies;

use App\Models\BackupDestination;
use App\Models\User;

class BackupDestinationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('backup-destinations.manage');
    }

    public function view(User $user, BackupDestination $backupDestination): bool
    {
        return $user->can('backup-destinations.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('backup-destinations.manage');
    }

    public function update(User $user, BackupDestination $backupDestination): bool
    {
        return $user->can('backup-destinations.manage');
    }

    public function delete(User $user, BackupDestination $backupDestination): bool
    {
        return $user->can('backup-destinations.manage');
    }

    public function test(User $user, BackupDestination $backupDestination): bool
    {
        return $user->can('backup-destinations.manage');
    }
}
