<?php

namespace App\Policies;

use App\Enums\MyDumper\MyDumperExportStatus;
use App\Models\MyDumperExport;
use App\Models\User;

class MyDumperExportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('mydumper-exports.view') || $user->can('mydumper-exports.manage');
    }

    public function view(User $user, MyDumperExport $export): bool
    {
        return $user->can('mydumper-exports.view') || $user->can('mydumper-exports.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('mydumper-exports.manage');
    }

    public function update(User $user, MyDumperExport $export): bool
    {
        return $user->can('mydumper-exports.manage');
    }

    public function delete(User $user, MyDumperExport $export): bool
    {
        return $user->can('mydumper-exports.delete')
            && $export->status->isFinished();
    }

    public function run(User $user, MyDumperExport $export): bool
    {
        return $user->can('mydumper-exports.run');
    }

    public function cancel(User $user, MyDumperExport $export): bool
    {
        return $user->can('mydumper-exports.run')
            && $export->status === MyDumperExportStatus::Running;
    }

    public function retry(User $user, MyDumperExport $export): bool
    {
        return $user->can('mydumper-exports.run')
            && in_array($export->status, [MyDumperExportStatus::Failed, MyDumperExportStatus::Cancelled], true);
    }

    public function download(User $user, MyDumperExport $export): bool
    {
        return $user->can('mydumper-exports.view')
            && $export->status === MyDumperExportStatus::Success;
    }
}
