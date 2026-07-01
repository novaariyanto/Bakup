<?php

namespace App\Repositories;

use App\Models\DatabaseConnection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DatabaseConnectionRepository
{
    public function paginate(?string $search = null, ?string $status = null, int $perPage = 10): LengthAwarePaginator
    {
        return DatabaseConnection::query()
            ->when($search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('host', 'like', "%{$search}%")
                        ->orWhere('database_name', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function find(int $id): ?DatabaseConnection
    {
        return DatabaseConnection::find($id);
    }

    public function create(array $data): DatabaseConnection
    {
        return DatabaseConnection::create($data);
    }

    public function update(DatabaseConnection $connection, array $data): DatabaseConnection
    {
        $connection->update($data);

        return $connection->fresh();
    }

    public function delete(DatabaseConnection $connection): void
    {
        $connection->delete();
    }
}
