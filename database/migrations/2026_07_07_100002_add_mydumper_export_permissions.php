<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'mydumper-exports.manage',
            'mydumper-exports.view',
            'mydumper-exports.run',
            'mydumper-exports.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::query()
            ->where('name', UserRole::Administrator->value)
            ->where('guard_name', 'web')
            ->first();

        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'mydumper-exports.manage',
            'mydumper-exports.view',
            'mydumper-exports.run',
            'mydumper-exports.delete',
        ];

        $role = Role::query()
            ->where('name', UserRole::Administrator->value)
            ->where('guard_name', 'web')
            ->first();

        if ($role) {
            $role->revokePermissionTo($permissions);
        }

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->delete();
    }
};
