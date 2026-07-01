<?php

namespace App\Services\Dashboard;

use App\Enums\BackupHistoryStatus;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Services\BaseService;
use Illuminate\Support\Carbon;

class DashboardService extends BaseService
{
    private const ACTIVITY_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function getOverview(): array
    {
        $since = now()->subDays(self::ACTIVITY_DAYS)->startOfDay();

        $storageUsed = (int) BackupHistory::query()
            ->where('status', BackupHistoryStatus::Success)
            ->sum('compressed_size_bytes');

        $nextProfile = BackupProfile::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->orderBy('next_run_at')
            ->first();

        $lastHistory = BackupHistory::query()
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->with('backupProfile')
            ->first();

        return [
            'stats' => [
                'profiles' => BackupProfile::query()->where('is_active', true)->count(),
                'profiles_total' => BackupProfile::query()->count(),
                'backups_total' => BackupHistory::query()->count(),
                'backups_success' => BackupHistory::query()
                    ->where('status', BackupHistoryStatus::Success)
                    ->where('finished_at', '>=', $since)
                    ->count(),
                'backups_failed' => BackupHistory::query()
                    ->where('status', BackupHistoryStatus::Failed)
                    ->where('finished_at', '>=', $since)
                    ->count(),
                'next_backup' => $nextProfile?->next_run_at?->timezone(config('app.timezone'))->format('d M Y H:i'),
                'next_backup_profile' => $nextProfile?->name,
                'last_backup' => $lastHistory?->finished_at?->timezone(config('app.timezone'))->format('d M Y H:i'),
                'last_backup_profile' => $lastHistory?->backupProfile?->name,
                'storage_used' => $storageUsed,
                'storage_used_label' => $this->formatBytes($storageUsed),
            ],
            'activity_chart' => $this->buildActivityChart($since),
            'recent_activity' => $this->buildRecentActivity(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildActivityChart(Carbon $since): array
    {
        $rows = BackupHistory::query()
            ->whereRaw('COALESCE(finished_at, created_at) >= ?', [$since])
            ->selectRaw('DATE(COALESCE(finished_at, created_at)) as day, status, COUNT(*) as total')
            ->groupByRaw('DATE(COALESCE(finished_at, created_at)), status')
            ->get();

        $grouped = $rows->groupBy('day');

        return collect(range(0, self::ACTIVITY_DAYS - 1))
            ->map(function (int $offset) use ($since, $grouped): array {
                $date = $since->copy()->addDays($offset);
                $dayKey = $date->toDateString();
                $dayRows = $grouped->get($dayKey, collect());

                $success = (int) $dayRows
                    ->where('status', BackupHistoryStatus::Success->value)
                    ->sum('total');
                $failed = (int) $dayRows
                    ->where('status', BackupHistoryStatus::Failed->value)
                    ->sum('total');

                return [
                    'date' => $dayKey,
                    'label' => $date->format('d M'),
                    'success' => $success,
                    'failed' => $failed,
                    'total' => $success + $failed,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRecentActivity(): array
    {
        return BackupHistory::query()
            ->with('backupProfile')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (BackupHistory $history): array => [
                'id' => $history->id,
                'profile_name' => $history->backupProfile?->name ?? '—',
                'status' => $history->status->value,
                'status_label' => $history->status->label(),
                'status_color' => $history->status->color(),
                'message' => $history->message,
                'filename' => $history->filename,
                'occurred_at' => ($history->finished_at ?? $history->created_at)
                    ?->timezone(config('app.timezone'))
                    ->format('d M Y H:i'),
                'relative_at' => ($history->finished_at ?? $history->created_at)?->diffForHumans(),
            ])
            ->all();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }
}
