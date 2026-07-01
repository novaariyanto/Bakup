<?php

namespace App\Services\Backup;

use App\Enums\BackupStage;
use App\Enums\CompressionType;
use App\Events\Backup\BackupCompleted;
use App\Events\Backup\BackupFailed;
use App\Events\Backup\BackupStarted;
use App\Exceptions\BackupExecutionException;
use App\Jobs\Backup\ExecuteBackupJob;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Repositories\BackupHistoryRepository;
use App\Services\BaseService;
use App\Support\BackupLogger;
use Throwable;

class BackupExecutionService extends BaseService
{
    public function __construct(
        private readonly BackupRuntimeConfigService $runtimeConfigService,
        private readonly BackupHistoryService $historyService,
        private readonly BackupHistoryRepository $historyRepository,
        private readonly BackupRetentionService $retentionService,
        private readonly BackupFileService $fileService,
        private readonly SpatieBackupRunner $spatieBackupRunner,
        private readonly DatabaseDumpBinaryResolver $dumpBinaryResolver,
        private readonly BackupLogger $logger,
    ) {}

    public function dispatch(BackupProfile $profile, ?int $userId = null): BackupHistory
    {
        $this->ensureProfileReady($profile);

        if ($this->historyRepository->hasRunningBackup($profile)) {
            throw BackupExecutionException::alreadyRunning();
        }

        $history = $this->historyService->createPending($profile, $userId);

        ExecuteBackupJob::dispatch($profile->id, $history->id);

        event(new BackupStarted($history));

        $this->logger->info('Backup job dispatched', [
            'profile_id' => $profile->id,
            'history_id' => $history->id,
            'user_id' => $userId,
        ]);

        return $history;
    }

    public function execute(BackupProfile $profile, BackupHistory $history): void
    {
        $this->ensureProfileReady($profile);
        $this->historyService->markRunning($history);

        try {
            $runtimeConfig = $this->runtimeConfigService->build($profile);

            $this->historyService->markStage($history, BackupStage::Preparing, 'Building runtime configuration.');
            $this->logGzipDumpFallback($profile, $history, $runtimeConfig);
            $this->historyService->markStage($history, BackupStage::Connecting, 'Connecting to database and storage.');
            $this->historyService->markStage($history, BackupStage::ReadingTables, 'Reading database tables.');

            if ($runtimeConfig->onlyDatabase || $profile->backup_database) {
                $this->ensureDumpBinaryAvailable();
            }

            $this->historyService->markStage($history, BackupStage::DumpingDatabase, 'Running Spatie backup.');
            $this->historyService->markStage($history, BackupStage::Compressing, 'Compressing backup archive.');
            $this->historyService->markStage($history, BackupStage::Uploading, 'Uploading to storage destinations.');

            $this->spatieBackupRunner->run(
                runtimeConfig: $runtimeConfig,
                onlyDatabase: $runtimeConfig->onlyDatabase,
                onlyFiles: $runtimeConfig->onlyFiles,
            );

            $this->historyService->markStage($history, BackupStage::Cleaning, 'Cleaning temporary files.');

            $storedFile = $this->fileService->findLatestBackupFile($profile);

            if ($storedFile === null) {
                throw BackupExecutionException::fileNotFoundAfterRun();
            }

            $completed = $this->historyService->markSuccess(
                history: $history,
                filename: basename($storedFile['path']),
                metadata: [
                    'destination_disks' => $runtimeConfig->destinationDiskNames,
                    'connection_name' => $runtimeConfig->connectionName,
                    'storage_path' => $storedFile['path'],
                ],
            );

            event(new BackupCompleted($completed));

            $this->retentionService->applyForProfile($profile);
        } catch (Throwable $exception) {
            $executionException = $this->normalizeExecutionException($exception);

            $failed = $this->historyService->markFailed(
                history: $history,
                message: $executionException->userMessage(),
                technicalMessage: $executionException->getMessage(),
            );

            event(new BackupFailed($failed, $executionException));

            $this->logger->error('Backup execution failed', [
                'profile_id' => $profile->id,
                'history_id' => $history->id,
                'error' => $executionException->getMessage(),
            ]);

            throw $executionException;
        }
    }

    private function ensureDumpBinaryAvailable(): void
    {
        if ($this->dumpBinaryResolver->mysqldumpExecutable() !== null) {
            return;
        }

        throw BackupExecutionException::mysqldumpNotFound();
    }

    private function normalizeExecutionException(Throwable $exception): BackupExecutionException
    {
        if ($exception instanceof BackupExecutionException) {
            return $exception;
        }

        return BackupExecutionException::fromDumpFailure($exception->getMessage());
    }

    private function logGzipDumpFallback(BackupProfile $profile, BackupHistory $history, \App\DTO\RuntimeBackupConfig $runtimeConfig): void
    {
        if (! $profile->compression->usesGzipDump()) {
            return;
        }

        if ($runtimeConfig->backupConfig['backup']['database_dump_compressor'] ?? null) {
            return;
        }

        $message = ! $this->dumpBinaryResolver->isGzipEnabled()
            ? 'GZIP dump dinonaktifkan (BACKUP_GZIP_ENABLED=false). Menggunakan dump SQL + arsip ZIP.'
            : 'Binary gzip tidak tersedia. Menggunakan dump SQL + arsip ZIP.';

        $this->historyService->addLog($history, BackupStage::Preparing, $message);
    }

    private function ensureProfileReady(BackupProfile $profile): void
    {
        $profile->loadMissing(['databaseConnection', 'destinations']);

        if (! $profile->is_active) {
            throw BackupExecutionException::profileInactive();
        }

        if (! $profile->databaseConnection?->is_active) {
            throw BackupExecutionException::connectionInactive();
        }

        $activeDestinations = $profile->destinations->filter(fn ($destination) => $destination->is_active);

        if ($activeDestinations->isEmpty()) {
            throw BackupExecutionException::noActiveDestinations();
        }
    }
}
