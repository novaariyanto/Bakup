<?php

namespace App\Policies;

use App\Models\DatabaseConnection;
use App\Models\User;

class DatabaseConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('database-connections.manage');
    }

    public function view(User $user, DatabaseConnection $databaseConnection): bool
    {
        return $user->can('database-connections.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('database-connections.manage');
    }

    public function update(User $user, DatabaseConnection $databaseConnection): bool
    {
        return $user->can('database-connections.manage');
    }

    public function delete(User $user, DatabaseConnection $databaseConnection): bool
    {
        return $user->can('database-connections.manage');
    }

    public function test(User $user, DatabaseConnection $databaseConnection): bool
    {
        return $user->can('database-connections.manage');
    }
}
