<?php

namespace App\Services\Backup;

use App\Enums\BackupHistoryStatus;
use App\Enums\BackupStage;
use App\Models\BackupHistory;
use App\Services\BaseService;
use Illuminate\Support\Facades\Cache;

class BackupProgressService extends BaseService
{
    /**
     * @return array<string, mixed>
     */
    public function forHistory(BackupHistory $history): array
    {
        $history->loadMissing('logs');

        $status = $history->status;

        $currentStage = $history->current_stage
            ?? Cache::get("backup:{$history->id}:stage");

        if (! $currentStage && in_array($status, [BackupHistoryStatus::Pending, BackupHistoryStatus::Running], true)) {
            $currentStage = BackupStage::Preparing->value;
        }

        $stageItems = $this->buildStageItems($currentStage);

        return [
            'history_id' => $history->id,
            'profile_name' => $history->metadata['profile_name'] ?? 'Backup Profile',
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'current_stage' => $currentStage,
            'current_stage_label' => BackupStage::tryFrom((string) $currentStage)?->label(),
            'percent' => BackupStage::progressPercent(is_string($currentStage) ? $currentStage : null),
            'is_finished' => in_array($status, [BackupHistoryStatus::Success, BackupHistoryStatus::Failed], true),
            'is_running' => in_array($status, [BackupHistoryStatus::Pending, BackupHistoryStatus::Running], true),
            'message' => $history->message,
            'filename' => $history->filename,
            'duration_seconds' => $history->duration_seconds,
            'stages' => $stageItems,
            'logs' => $history->logs->map(fn ($log) => [
                'stage' => $log->stage,
                'level' => $log->level,
                'message' => $log->message,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildStageItems(?string $currentStage): array
    {
        $current = BackupStage::tryFrom((string) $currentStage);
        $currentOrder = $current?->order() ?? 0;

        return array_map(function (BackupStage $stage) use ($currentOrder, $current): array {
            $state = 'pending';

            if ($current && $stage->order() < $currentOrder) {
                $state = 'completed';
            } elseif ($current && $stage === $current) {
                $state = 'current';
            } elseif ($current === BackupStage::Finished && $stage === BackupStage::Finished) {
                $state = 'completed';
            }

            return [
                'key' => $stage->value,
                'label' => $stage->label(),
                'state' => $state,
            ];
        }, BackupStage::ordered());
    }
}
