<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'database-connections.manage',
            'backup-profiles.manage',
            'backup-destinations.manage',
            'backup-history.view',
            'backup-history.run',
            'notifications.manage',
            'activity.view',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate([
            'name' => UserRole::Administrator->value,
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($permissions);

        $user = User::firstOrCreate(
            ['email' => 'admin@backupmanager.test'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'timezone' => 'Asia/Jakarta',
            ]
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        $localAdmin = User::firstOrCreate(
            ['email' => 'admin@local.test'],
            [
                'name' => 'Admin Local',
                'password' => Hash::make('Admin123!'),
                'timezone' => 'Asia/Jakarta',
            ]
        );

        if (! $localAdmin->hasRole($role)) {
            $localAdmin->assignRole($role);
        }
    }
}
