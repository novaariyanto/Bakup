<?php

namespace App\Policies;

use App\Models\NotificationChannel;
use App\Models\User;

class NotificationChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('notifications.manage');
    }

    public function view(User $user, NotificationChannel $notificationChannel): bool
    {
        return $user->can('notifications.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('notifications.manage');
    }

    public function update(User $user, NotificationChannel $notificationChannel): bool
    {
        return $user->can('notifications.manage');
    }

    public function delete(User $user, NotificationChannel $notificationChannel): bool
    {
        return $user->can('notifications.manage');
    }

    public function test(User $user, NotificationChannel $notificationChannel): bool
    {
        return $user->can('notifications.manage');
    }
}
