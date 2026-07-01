<?php

namespace App\DTO;

readonly class RuntimeBackupConfig
{
    /**
     * @param  list<string>  $destinationDiskNames
     * @param  array<string, mixed>  $databaseConnectionConfig
     * @param  array<string, array<string, mixed>>  $filesystemDiskConfigs
     * @param  array<string, mixed>  $backupConfig
     */
    public function __construct(
        public string $connectionName,
        public array $destinationDiskNames,
        public array $databaseConnectionConfig,
        public array $filesystemDiskConfigs,
        public array $backupConfig,
        public bool $onlyDatabase,
        public bool $onlyFiles,
    ) {}
}
