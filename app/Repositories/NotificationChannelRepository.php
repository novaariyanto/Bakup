<?php

namespace App\Repositories;

use App\Models\NotificationChannel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationChannelRepository
{
    public function paginate(
        ?string $search = null,
        ?string $status = null,
        ?string $driver = null,
        int $perPage = 10,
    ): LengthAwarePaginator {
        return NotificationChannel::query()
            ->when($search, function ($query, string $search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($driver && $driver !== 'all', fn ($query) => $query->where('driver', $driver))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, NotificationChannel>
     */
    public function activeForEvent(string $event): \Illuminate\Database\Eloquent\Collection
    {
        return NotificationChannel::query()
            ->where('is_active', true)
            ->when($event === 'success', fn ($query) => $query->where('notify_on_success', true))
            ->when($event === 'failure', fn ($query) => $query->where('notify_on_failure', true))
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?NotificationChannel
    {
        return NotificationChannel::find($id);
    }

    public function create(array $data): NotificationChannel
    {
        return NotificationChannel::create($data);
    }

    public function update(NotificationChannel $channel, array $data): NotificationChannel
    {
        $channel->update($data);

        return $channel->fresh();
    }

    public function delete(NotificationChannel $channel): void
    {
        $channel->delete();
    }
}
