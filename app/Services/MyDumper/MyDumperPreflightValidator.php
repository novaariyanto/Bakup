<?php

namespace App\Services\MyDumper;

use App\Exceptions\MyDumper\MyDumperException;
use App\Models\BackupDestination;
use App\Models\DatabaseConnection;
use App\Models\MyDumperExportProfile;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MyDumperPreflightValidator extends BaseService
{
    public function __construct(
        private readonly MyDumperBinaryResolver $binaryResolver,
    ) {}

    public function validateProfile(MyDumperExportProfile $profile): void
    {
        if (! $profile->is_active) {
            throw MyDumperException::profileInactive();
        }

        $connection = $profile->databaseConnection;

        if (! $connection || ! $connection->is_active) {
            throw MyDumperException::connectionFailed('Koneksi database tidak aktif.');
        }

        $destination = $profile->storageDestination;

        if (! $destination || ! $destination->is_active) {
            throw MyDumperException::destinationInactive();
        }

        $this->validateBinary();
        $this->validateConnection($connection, $profile->resolvedDatabase());
        $this->validateStagingDirectory();
    }

    public function validateDestination(BackupDestination $destination): void
    {
        if (! $destination->is_active) {
            throw MyDumperException::destinationInactive();
        }
    }

    public function validateBinary(): void
    {
        if ($this->binaryResolver->resolve() === null) {
            throw MyDumperException::notInstalled();
        }

        if (! $this->binaryResolver->isCompatible()) {
            throw MyDumperException::incompatibleVersion(
                $this->binaryResolver->version() ?? 'unknown',
                config('mydumper.min_version', '0.10.0'),
            );
        }
    }

    public function validateConnection(DatabaseConnection $connection, string $database): void
    {
        try {
            config([
                'database.connections.mydumper_preflight' => [
                    'driver' => 'mysql',
                    'host' => $connection->host,
                    'port' => $connection->port,
                    'database' => $database,
                    'username' => $connection->username,
                    'password' => $connection->password,
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ],
            ]);

            DB::connection('mydumper_preflight')->getPdo();
        } catch (\Throwable $exception) {
            throw MyDumperException::connectionFailed($exception->getMessage());
        } finally {
            DB::purge('mydumper_preflight');
        }
    }

    public function validateStagingDirectory(?string $subPath = null): string
    {
        $root = config('mydumper.staging_root');
        $path = $subPath ? $root.DIRECTORY_SEPARATOR.$subPath : $root;

        File::ensureDirectoryExists($path);

        if (! is_writable($path)) {
            throw MyDumperException::permissionDenied($path);
        }

        $freeBytes = @disk_free_space($path);

        if ($freeBytes !== false && $freeBytes < 100 * 1024 * 1024) {
            throw MyDumperException::diskFull();
        }

        return $path;
    }
}
