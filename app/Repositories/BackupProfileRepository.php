<?php

namespace App\Repositories;

use App\Models\BackupProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BackupProfileRepository
{
    public function paginate(
        ?string $search = null,
        ?string $status = null,
        ?int $connectionId = null,
        int $perPage = 10,
    ): LengthAwarePaginator {
        return BackupProfile::query()
            ->with(['databaseConnection', 'destinations'])
            ->when($search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($connectionId, fn ($query) => $query->where('database_connection_id', $connectionId))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function find(int $id): ?BackupProfile
    {
        return BackupProfile::with([
            'databaseConnection',
            'destinations',
            'excludedTables',
            'includeFolders',
            'excludeFolders',
        ])->find($id);
    }

    public function create(array $data): BackupProfile
    {
        return BackupProfile::create($data);
    }

    public function update(BackupProfile $profile, array $data): BackupProfile
    {
        $profile->update($data);

        return $profile->fresh([
            'databaseConnection',
            'destinations',
            'excludedTables',
            'includeFolders',
            'excludeFolders',
        ]);
    }

    public function delete(BackupProfile $profile): void
    {
        $profile->delete();
    }
}
