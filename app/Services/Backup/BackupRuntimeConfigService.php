<?php

namespace App\Services\Backup;

use App\DTO\RuntimeBackupConfig;
use App\Enums\CompressionType;
use App\Models\BackupDestination;
use App\Enums\TableDumpMode;
use App\Models\BackupProfile;
use App\Services\BaseService;
use App\Services\Storage\StorageDriverManager;
use App\Support\Backup\Compressors\ResolvableGzipCompressor;
use Illuminate\Support\Str;
use Spatie\Backup\Config\Config;
use ZipArchive;

class BackupRuntimeConfigService extends BaseService
{
    public function __construct(
        private readonly StorageDriverManager $storageDriverManager,
        private readonly DatabaseDumpBinaryResolver $dumpBinaryResolver,
        private readonly MySqlDumpConnectionResolver $connectionResolver,
        private readonly DatabaseBackupSchemaService $schemaService,
    ) {}

    public function build(BackupProfile $profile): RuntimeBackupConfig
    {
        $profile->loadMissing([
            'databaseConnection',
            'destinations',
            'excludedTables',
            'includeFolders',
            'excludeFolders',
        ]);

        $connection = $profile->databaseConnection;
        $connectionName = $this->connectionName($profile->id);

        $structureOnlyTables = $profile->excludedTables
            ->where('dump_mode', TableDumpMode::StructureOnly)
            ->pluck('table_name')
            ->all();

        $explicitExcludedTables = $profile->excludedTables
            ->where('dump_mode', TableDumpMode::Exclude)
            ->pluck('table_name')
            ->all();

        $dumpOptions = [
            'exclude_tables' => array_values(array_unique(array_merge(
                $this->resolveExcludedTables($profile),
                $explicitExcludedTables,
            ))),
            'structure_only_tables' => $structureOnlyTables,
            'useSingleTransaction' => true,
            'dump_binary_path' => $this->dumpBinaryResolver->resolve(),
        ];

        if ($profile->include_stored_procedures) {
            $dumpOptions['addExtraOption'] = '--routines';
        }

        if ($profile->include_views) {
            $dumpOptions['includeViews'] = true;
        }

        if ($this->dumpBinaryResolver->shouldDisableColumnStatistics()) {
            $dumpOptions['doNotUseColumnStatistics'] = true;
        }

        $databaseConnectionConfig = [
            'driver' => 'mysql',
            'host' => $this->connectionResolver->resolveDumpHost($connection->host),
            'port' => $connection->port,
            'database' => $connection->database_name,
            'username' => $connection->username,
            'password' => $connection->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'dump' => array_filter(
                $dumpOptions,
                fn (mixed $value) => $value !== null && $value !== [] && $value !== '',
            ),
        ];

        if ($socket = $this->connectionResolver->resolveSocket($connection->host)) {
            $databaseConnectionConfig['unix_socket'] = $socket;
        }

        $filesystemDiskConfigs = [];
        $destinationDiskNames = [];

        foreach ($profile->destinations->where('is_active', true) as $destination) {
            $diskName = $this->destinationDiskName($profile->id, $destination->id);
            $destinationDiskNames[] = $diskName;
            $filesystemDiskConfigs[$diskName] = $this->storageDriverManager
                ->driver($destination->driver)
                ->toFilesystemConfig($destination->config ?? []);
        }

        $baseBackupConfig = config('backup');
        $onlyDatabase = $profile->backup_database && ! $profile->backup_folders;
        $onlyFiles = $profile->backup_folders && ! $profile->backup_database;

        $backupConfig = array_replace_recursive($baseBackupConfig, [
            'backup' => [
                'name' => $profile->uuid,
                'source' => [
                    'files' => [
                        'include' => $this->resolveIncludePaths($profile),
                        'exclude' => $this->resolveExcludePaths($profile),
                        'follow_links' => false,
                        'ignore_unreadable_directories' => true,
                        'relative_path' => base_path(),
                    ],
                    'databases' => $profile->backup_database ? [$connectionName] : [],
                ],
                'database_dump_compressor' => $this->resolveDatabaseCompressor($profile->compression),
                'destination' => [
                    'compression_method' => $this->resolveArchiveCompressionMethod($profile->compression),
                    'compression_level' => 9,
                    'filename_prefix' => Str::slug($profile->name).'-',
                    'disks' => $destinationDiskNames,
                ],
                'temporary_directory' => storage_path('app/backup-temp/'.$profile->uuid),
                'tries' => 1,
            ],
        ]);

        if (! $profile->backup_database) {
            $backupConfig['backup']['source']['databases'] = [];
        }

        if (! $profile->backup_folders) {
            $backupConfig['backup']['source']['files']['include'] = [];
        }

        return new RuntimeBackupConfig(
            connectionName: $connectionName,
            destinationDiskNames: $destinationDiskNames,
            databaseConnectionConfig: $databaseConnectionConfig,
            filesystemDiskConfigs: $filesystemDiskConfigs,
            backupConfig: $backupConfig,
            onlyDatabase: $onlyDatabase,
            onlyFiles: $onlyFiles,
        );
    }

    public function apply(RuntimeBackupConfig $runtimeConfig): void
    {
        config([
            'database.connections.'.$runtimeConfig->connectionName => $runtimeConfig->databaseConnectionConfig,
        ]);

        foreach ($runtimeConfig->filesystemDiskConfigs as $diskName => $diskConfig) {
            config([
                'filesystems.disks.'.$diskName => $diskConfig,
            ]);
        }

        config([
            'backup' => $runtimeConfig->backupConfig,
        ]);
    }

    public function refreshSpatieRuntime(): void
    {
        if (! class_exists(Config::class)) {
            return;
        }

        Config::rebind();
        app()->forgetInstance('backup-temporary-project');
    }

    public function connectionName(int $profileId): string
    {
        return 'bm_profile_'.$profileId;
    }

    public function destinationDiskName(int $profileId, int $destinationId): string
    {
        return 'bm_profile_'.$profileId.'_dest_'.$destinationId;
    }

    /**
     * @return list<string>
     */
    private function resolveIncludePaths(BackupProfile $profile): array
    {
        return $profile->includeFolders
            ->pluck('path')
            ->map(fn (string $path) => $this->toAbsolutePath($path))
            ->filter(fn (string $path) => is_dir($path) || file_exists($path))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function resolveExcludePaths(BackupProfile $profile): array
    {
        $defaults = [
            base_path('vendor'),
            base_path('node_modules'),
            storage_path('framework'),
            storage_path('logs'),
        ];

        $profileExcludes = $profile->excludeFolders
            ->pluck('path')
            ->map(fn (string $path) => $this->toAbsolutePath($path))
            ->all();

        return array_values(array_unique(array_merge($defaults, $profileExcludes)));
    }

    private function toAbsolutePath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path, '/\\'));

        if ($normalized === '') {
            return base_path();
        }

        if (preg_match('/^[A-Za-z]:\\//', $normalized) || str_starts_with($normalized, '/')) {
            return $normalized;
        }

        return base_path($normalized);
    }

    private function resolveDatabaseCompressor(CompressionType $compression): ?string
    {
        if (! $compression->usesGzipDump()) {
            return null;
        }

        if (! $this->dumpBinaryResolver->isGzipEnabled()) {
            return null;
        }

        return $this->dumpBinaryResolver->isGzipAvailable()
            ? ResolvableGzipCompressor::class
            : null;
    }

    private function resolveArchiveCompressionMethod(CompressionType $compression): int
    {
        return match ($compression) {
            CompressionType::None => ZipArchive::CM_STORE,
            CompressionType::Gzip, CompressionType::Zip => ZipArchive::CM_DEFAULT,
        };
    }

    /**
     * @return list<string>
     */
    private function resolveExcludedTables(BackupProfile $profile): array
    {
        $excluded = [];

        if ($profile->backup_database && ! $profile->include_views) {
            $excluded = array_merge(
                $excluded,
                $this->schemaService->fetchViewNames($profile->databaseConnection),
            );
        }

        return array_values(array_unique($excluded));
    }
}
