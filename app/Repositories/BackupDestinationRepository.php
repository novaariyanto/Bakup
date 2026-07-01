<?php

namespace App\Repositories;

use App\Models\BackupDestination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BackupDestinationRepository
{
    public function paginate(?string $search = null, ?string $status = null, ?string $driver = null, int $perPage = 10): LengthAwarePaginator
    {
        return BackupDestination::query()
            ->when($search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($driver && $driver !== 'all', fn ($query) => $query->where('driver', $driver))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function find(int $id): ?BackupDestination
    {
        return BackupDestination::find($id);
    }

    public function create(array $data): BackupDestination
    {
        return BackupDestination::create($data);
    }

    public function update(BackupDestination $destination, array $data): BackupDestination
    {
        $destination->update($data);

        return $destination->fresh();
    }

    public function delete(BackupDestination $destination): void
    {
        $destination->delete();
    }
}
