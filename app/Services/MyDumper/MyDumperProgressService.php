<?php

namespace App\Services\MyDumper;

use App\Enums\MyDumper\MyDumperExportStage;
use App\Enums\MyDumper\MyDumperExportStatus;
use App\Models\MyDumperExport;
use App\Services\BaseService;

class MyDumperProgressService extends BaseService
{
    /**
     * @return array<string, mixed>
     */
    public function forExport(MyDumperExport $export): array
    {
        $export->loadMissing(['connection', 'profile', 'storageDestination']);

        $startedAt = $export->started_at;
        $elapsed = $startedAt ? $startedAt->diffInSeconds(now()) : 0;
        $eta = $export->eta_seconds;

        if ($eta === null && $export->progress_percent > 0 && $elapsed > 0) {
            $eta = (int) round(($elapsed / max(1, $export->progress_percent)) * (100 - $export->progress_percent));
        }

        $speedMbps = null;

        if ($export->total_size && $elapsed > 0) {
            $speedMbps = round(($export->total_size / 1024 / 1024) / $elapsed, 2);
        }

        $status = $export->status ?? MyDumperExportStatus::Waiting;

        return [
            'id' => $export->id,
            'uuid' => $export->uuid,
            'name' => $export->name,
            'status' => $status->value,
            'status_label' => $status->label(),
            'current_stage' => $export->current_stage?->value,
            'current_stage_label' => $export->current_stage?->label() ?? null,
            'progress_percent' => $export->progress_percent,
            'current_table' => $export->current_table,
            'current_file' => $export->current_file,
            'rows_exported' => $export->rows_exported,
            'tables_total' => $export->tables_total,
            'tables_completed' => $export->tables_completed,
            'remaining_tables' => $export->tables_total && $export->tables_completed
                ? max(0, $export->tables_total - $export->tables_completed)
                : null,
            'elapsed_seconds' => $elapsed,
            'eta_seconds' => $eta,
            'speed_mbps' => $speedMbps,
            'started_at' => $export->started_at?->toIso8601String(),
            'finished_at' => $export->finished_at?->toIso8601String(),
            'is_finished' => $status->isFinished(),
            'message' => $export->message,
        ];
    }
}
