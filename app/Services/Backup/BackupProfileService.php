<?php

namespace App\Services\Backup;

use App\DTO\DatabaseTableInfo;
use App\Enums\TableDumpMode;
use App\Exceptions\BackupProfileException;
use App\Models\BackupProfile;
use App\Models\DatabaseConnection;
use App\Repositories\BackupProfileRepository;
use App\Services\BaseService;
use App\Services\Database\DatabaseConnectionService;
use App\Services\Schedule\ScheduleService;
use App\Support\BackupLogger;
use Illuminate\Support\Facades\DB;

class BackupProfileService extends BaseService
{
    public function __construct(
        private readonly BackupProfileRepository $repository,
        private readonly DatabaseConnectionService $connectionService,
        private readonly ScheduleService $scheduleService,
        private readonly BackupLogger $logger,
    ) {}

    public function create(array $data): BackupProfile
    {
        $this->validateProfileData($data);

        $profile = DB::transaction(function () use ($data) {
            $profile = $this->repository->create($this->extractProfileAttributes($data));
            $this->syncRelations($profile, $data);

            return $profile->fresh([
                'databaseConnection',
                'destinations',
                'excludedTables',
                'includeFolders',
                'excludeFolders',
            ]);
        });

        $profile = $this->scheduleService->syncNextRunAt($profile);

        $this->logger->info('Backup profile created', [
            'profile_id' => $profile->id,
            'name' => $profile->name,
        ]);

        return $profile;
    }

    public function update(BackupProfile $profile, array $data): BackupProfile
    {
        $this->validateProfileData($data);

        $updated = DB::transaction(function () use ($profile, $data) {
            $updated = $this->repository->update($profile, $this->extractProfileAttributes($data));
            $this->syncRelations($updated, $data);

            return $updated;
        });

        $updated = $this->scheduleService->syncNextRunAt($updated);

        $this->logger->info('Backup profile updated', [
            'profile_id' => $updated->id,
            'name' => $updated->name,
        ]);

        return $updated;
    }

    public function delete(BackupProfile $profile): void
    {
        $this->repository->delete($profile);

        $this->logger->info('Backup profile deleted', [
            'profile_id' => $profile->id,
            'name' => $profile->name,
        ]);
    }

    /**
     * @return list<DatabaseTableInfo>
     */
    public function fetchTablesForConnection(DatabaseConnection $connection): array
    {
        if (! $connection->is_active) {
            throw BackupProfileException::connectionUnavailable();
        }

        return $this->connectionService->fetchTables($connection);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateProfileData(array $data): void
    {
        $backupDatabase = (bool) ($data['backup_database'] ?? false);
        $backupFolders = (bool) ($data['backup_folders'] ?? false);

        if (! $backupDatabase && ! $backupFolders) {
            throw BackupProfileException::backupTypeRequired();
        }

        $destinationIds = array_filter($data['destination_ids'] ?? []);

        if ($destinationIds === []) {
            throw BackupProfileException::noDestinationsSelected();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractProfileAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'database_connection_id' => $data['database_connection_id'],
            'backup_database' => (bool) ($data['backup_database'] ?? false),
            'backup_folders' => (bool) ($data['backup_folders'] ?? false),
            'include_stored_procedures' => (bool) ($data['include_stored_procedures'] ?? false),
            'include_views' => (bool) ($data['include_views'] ?? false),
            'compression' => $data['compression'],
            'schedule_type' => $data['schedule_type'],
            'schedule_cron' => $data['schedule_cron'] ?? null,
            'schedule_time' => $data['schedule_time'] ?? null,
            'schedule_day_of_week' => $data['schedule_day_of_week'] ?? null,
            'schedule_day_of_month' => $data['schedule_day_of_month'] ?? null,
            'retention_type' => $data['retention_type'],
            'retention_value' => (int) ($data['retention_value'] ?? 7),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(BackupProfile $profile, array $data): void
    {
        $tableDumpModes = $this->normalizeTableDumpModes($data['table_dump_modes'] ?? []);
        $includeFolders = $this->normalizePaths($data['include_folders'] ?? []);
        $excludeFolders = $this->normalizePaths($data['exclude_folders'] ?? []);
        $destinationIds = array_values(array_filter($data['destination_ids'] ?? []));

        $profile->excludedTables()->delete();
        if ($tableDumpModes !== []) {
            $profile->excludedTables()->createMany(
                array_map(
                    fn (array $table) => [
                        'table_name' => $table['table_name'],
                        'dump_mode' => $table['dump_mode'],
                    ],
                    $tableDumpModes,
                ),
            );
        }

        $profile->includeFolders()->delete();
        if ($includeFolders !== []) {
            $profile->includeFolders()->createMany(
                array_map(fn (string $path) => ['path' => $path], $includeFolders)
            );
        }

        $profile->excludeFolders()->delete();
        if ($excludeFolders !== []) {
            $profile->excludeFolders()->createMany(
                array_map(fn (string $path) => ['path' => $path], $excludeFolders)
            );
        }

        $syncData = [];
        foreach ($destinationIds as $index => $destinationId) {
            $syncData[(int) $destinationId] = ['sort_order' => $index];
        }

        $profile->destinations()->sync($syncData);
    }

    /**
     * @param  array<string, mixed>  $modes
     * @return list<array{table_name: string, dump_mode: string}>
     */
    private function normalizeTableDumpModes(array $modes): array
    {
        $normalized = [];

        foreach ($modes as $tableName => $mode) {
            $tableName = trim((string) $tableName);
            $mode = trim((string) $mode);

            if ($tableName === '' || $mode === TableDumpMode::WithData->value) {
                continue;
            }

            $dumpMode = TableDumpMode::tryFrom($mode);

            if ($dumpMode === null || $dumpMode === TableDumpMode::WithData) {
                continue;
            }

            $normalized[] = [
                'table_name' => $tableName,
                'dump_mode' => $dumpMode->value,
            ];
        }

        return $normalized;
    }

    private function normalizePaths(array $paths): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($path) => trim(str_replace('\\', '/', (string) $path), '/'),
            $paths,
        ))));
    }
}
