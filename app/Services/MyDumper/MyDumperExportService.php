<?php

namespace App\Services\MyDumper;

use App\DTO\MyDumper\MyDumperExportOptions;
use App\Enums\MyDumper\MyDumperExportStatus;
use App\Enums\MyDumper\MyDumperExportType;
use App\Enums\ScheduleType;
use App\Models\MyDumperExport;
use App\Models\MyDumperExportProfile;
use App\Repositories\MyDumperExportRepository;
use App\Services\BaseService;
use App\Services\Schedule\ScheduleService;
use Illuminate\Support\Facades\DB;

class MyDumperExportService extends BaseService
{
    public function __construct(
        private readonly MyDumperExportRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createProfile(array $payload, ?int $userId = null): MyDumperExportProfile
    {
        return DB::transaction(function () use ($payload, $userId) {
            $profile = MyDumperExportProfile::create([
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'database_connection_id' => $payload['database_connection_id'],
                'database' => $payload['database'] ?? null,
                'storage_destination_id' => $payload['storage_destination_id'],
                'export_type' => $payload['export_type'],
                'options' => $payload['options'] ?? [],
                'selected_tables' => $payload['selected_tables'] ?? null,
                'exclude_tables' => $payload['exclude_tables'] ?? null,
                'output_folder' => $payload['output_folder'] ?? null,
                'threads' => $payload['threads'] ?? config('mydumper.default_threads', 4),
                'compression' => (bool) ($payload['compression'] ?? false),
                'schedule_type' => $payload['schedule_type'] ?? ScheduleType::Manual->value,
                'schedule_cron' => $payload['schedule_cron'] ?? null,
                'schedule_time' => $payload['schedule_time'] ?? null,
                'schedule_day_of_week' => $payload['schedule_day_of_week'] ?? null,
                'schedule_day_of_month' => $payload['schedule_day_of_month'] ?? null,
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'created_by' => $userId,
            ]);

            return app(MyDumperScheduleService::class)->syncNextRunAt($profile);
        });
    }

    public function createExportFromProfile(MyDumperExportProfile $profile, ?int $userId = null): MyDumperExport
    {
        $optionsSnapshot = array_merge(
            MyDumperExportOptions::fromArray($profile->options)->toArray(),
            [
                'selected_tables' => $profile->selected_tables,
                'exclude_tables' => $profile->exclude_tables,
            ],
        );

        return $this->repository->create([
            'profile_id' => $profile->id,
            'connection_id' => $profile->database_connection_id,
            'storage_destination_id' => $profile->storage_destination_id,
            'name' => $profile->name,
            'database' => $profile->resolvedDatabase(),
            'type' => $profile->export_type,
            'status' => MyDumperExportStatus::Waiting,
            'thread' => $profile->threads,
            'compression' => $profile->compression,
            'options_snapshot' => $optionsSnapshot,
            'created_by' => $userId,
        ]);
    }

    public function deleteExport(MyDumperExport $export): void
    {
        $this->repository->delete($export);
    }

    /**
     * @param  array<int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        return MyDumperExport::query()
            ->whereIn('id', $ids)
            ->whereIn('status', [
                MyDumperExportStatus::Success->value,
                MyDumperExportStatus::Failed->value,
                MyDumperExportStatus::Cancelled->value,
            ])
            ->delete();
    }
}
