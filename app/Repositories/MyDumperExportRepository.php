<?php

namespace App\Repositories;

use App\Enums\MyDumper\MyDumperExportStatus;
use App\Models\MyDumperExport;
use App\Models\MyDumperExportProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class MyDumperExportRepository
{
    public function paginate(
        ?string $search = null,
        ?string $status = null,
        ?int $connectionId = null,
        ?int $profileId = null,
        string $sort = 'created_at',
        string $direction = 'desc',
        int $perPage = 15,
    ): LengthAwarePaginator {
        $allowedSorts = ['id', 'created_at', 'started_at', 'total_size', 'status', 'name'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return MyDumperExport::query()
            ->with(['profile', 'connection', 'storageDestination', 'creator'])
            ->when($search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('database', 'like', "%{$search}%")
                        ->orWhereHas('profile', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($connectionId, fn ($query) => $query->where('connection_id', $connectionId))
            ->when($profileId, fn ($query) => $query->where('profile_id', $profileId))
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }

    public function findWithRelations(int $id): ?MyDumperExport
    {
        return MyDumperExport::with([
            'profile',
            'connection',
            'storageDestination',
            'creator',
            'logs',
            'files',
        ])->find($id);
    }

    public function create(array $data): MyDumperExport
    {
        return MyDumperExport::create($data);
    }

    public function update(MyDumperExport $export, array $data): MyDumperExport
    {
        $export->update($data);

        return $export->fresh();
    }

    public function delete(MyDumperExport $export): void
    {
        $export->delete();
    }

    public function hasRunningExport(?MyDumperExportProfile $profile = null): bool
    {
        $query = MyDumperExport::query()->where('status', MyDumperExportStatus::Running);

        if ($profile !== null) {
            $query->where('profile_id', $profile->id);
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardStats(): array
    {
        $today = Carbon::today();

        $running = MyDumperExport::query()->where('status', MyDumperExportStatus::Running)->count();
        $queued = MyDumperExport::query()->where('status', MyDumperExportStatus::Waiting)->count();
        $successToday = MyDumperExport::query()
            ->where('status', MyDumperExportStatus::Success)
            ->whereDate('finished_at', '>=', $today)
            ->count();
        $failedToday = MyDumperExport::query()
            ->where('status', MyDumperExportStatus::Failed)
            ->whereDate('finished_at', '>=', $today)
            ->count();

        $totalSizeToday = (int) MyDumperExport::query()
            ->where('status', MyDumperExportStatus::Success)
            ->whereDate('finished_at', '>=', $today)
            ->sum('total_size');

        $avgSpeed = MyDumperExport::query()
            ->where('status', MyDumperExportStatus::Success)
            ->whereDate('finished_at', '>=', $today)
            ->whereNotNull('duration')
            ->where('duration', '>', 0)
            ->whereNotNull('total_size')
            ->get()
            ->avg(fn (MyDumperExport $export) => ($export->total_size / 1024 / 1024) / max(1, $export->duration));

        return [
            'running' => $running,
            'queued' => $queued,
            'success_today' => $successToday,
            'failed_today' => $failedToday,
            'total_size_today' => $totalSizeToday,
            'average_speed_mbps' => round((float) ($avgSpeed ?? 0), 2),
        ];
    }
}
