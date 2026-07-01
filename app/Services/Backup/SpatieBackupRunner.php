<?php

namespace App\Services\Backup;

use App\DTO\RuntimeBackupConfig;
use App\Services\BaseService;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;

class SpatieBackupRunner extends BaseService
{
    public function __construct(
        private readonly BackupRuntimeConfigService $runtimeConfigService,
        private readonly DatabaseDumpBinaryResolver $dumpBinaryResolver,
    ) {}

    public function run(RuntimeBackupConfig $runtimeConfig, bool $onlyDatabase, bool $onlyFiles): void
    {
        $this->dumpBinaryResolver->applyProcessPath();
        $this->runtimeConfigService->apply($runtimeConfig);
        $this->runtimeConfigService->refreshSpatieRuntime();

        $backupJob = BackupJobFactory::createFromConfig(app(Config::class));

        if ($onlyDatabase) {
            $backupJob->dontBackupFilesystem();
        }

        if ($onlyFiles) {
            $backupJob->dontBackupDatabases();
        }

        $backupJob
            ->disableNotifications()
            ->disableSignals()
            ->run();
    }
}
