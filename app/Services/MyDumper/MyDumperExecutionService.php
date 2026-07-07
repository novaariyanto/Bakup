<?php

namespace App\Services\MyDumper;

use App\Enums\MyDumper\MyDumperExportStage;
use App\Enums\MyDumper\MyDumperExportStatus;
use App\Enums\ScheduleType;
use App\Events\MyDumper\ExportCancelled;
use App\Events\MyDumper\ExportCompleted;
use App\Events\MyDumper\ExportFailed;
use App\Events\MyDumper\ExportStarted;
use App\Exceptions\MyDumper\MyDumperException;
use App\Jobs\MyDumper\RunMyDumperExportJob;
use App\Models\MyDumperExport;
use App\Models\MyDumperExportProfile;
use App\Repositories\MyDumperExportRepository;
use App\Services\BaseService;
use App\Support\MyDumperLogger;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Throwable;

class MyDumperExecutionService extends BaseService
{
    public function __construct(
        private readonly MyDumperExportRepository $repository,
        private readonly MyDumperExportService $exportService,
        private readonly MyDumperPreflightValidator $preflightValidator,
        private readonly MyDumperLogService $logService,
        private readonly MyDumperRunner $runner,
        private readonly MyDumperStorageUploader $uploader,
        private readonly MyDumperVerificationService $verificationService,
        private readonly MyDumperLogger $logger,
    ) {}

    public function dispatchFromProfile(MyDumperExportProfile $profile, ?int $userId = null, bool $runImmediately = true): MyDumperExport
    {
        $this->preflightValidator->validateProfile($profile);

        if ($this->repository->hasRunningExport($profile)) {
            throw MyDumperException::alreadyRunning();
        }

        $export = $this->exportService->createExportFromProfile($profile, $userId);

        if ($runImmediately) {
            $this->dispatchChain($export);
        }

        return $export;
    }

    public function dispatchChain(MyDumperExport $export): void
    {
        $this->logService->initialize($export);
        $this->runner->clearCancelFlag($export->id);

        Bus::chain([
            new RunMyDumperExportJob($export->id),
            new \App\Jobs\MyDumper\UploadExportJob($export->id),
            new \App\Jobs\MyDumper\VerifyExportJob($export->id),
            new \App\Jobs\MyDumper\CleanupExportJob($export->id),
        ])->dispatch();

        event(new ExportStarted($export));
    }

    public function runExport(MyDumperExport $export): void
    {
        $export = $export->fresh(['connection', 'profile']);

        $this->markRunning($export, MyDumperExportStage::Validating);
        $this->preflightValidator->validateBinary();

        $stagingPath = $this->preflightValidator->validateStagingDirectory($export->uuid);

        $this->markStage($export, MyDumperExportStage::Dumping, 10);
        $startedAt = now();
        $export->update(['started_at' => $startedAt]);

        try {
            $exitCode = $this->runner->run($export, $stagingPath);
            $finishedAt = now();

            $export->update([
                'exit_code' => $exitCode,
                'duration' => $startedAt->diffInSeconds($finishedAt),
            ]);
        } catch (MyDumperException $exception) {
            $this->markFailed($export, $exception);

            throw $exception;
        } catch (Throwable $exception) {
            $this->markFailed($export, $exception);

            throw $exception;
        }
    }

    public function uploadExport(MyDumperExport $export): void
    {
        $export = $export->fresh();
        $this->markStage($export, MyDumperExportStage::Uploading, 75);

        try {
            $this->uploader->upload($export);
        } catch (Throwable $exception) {
            $this->markFailed($export, $exception);

            throw $exception;
        }
    }

    public function verifyExport(MyDumperExport $export): void
    {
        $export = $export->fresh();
        $this->markStage($export, MyDumperExportStage::Verifying, 90);

        try {
            $this->verificationService->verify($export);
            $this->markSuccess($export);
        } catch (Throwable $exception) {
            $this->markFailed($export, $exception);

            throw $exception;
        }
    }

    public function cleanupExport(MyDumperExport $export): void
    {
        if ($export->output_path && File::isDirectory($export->output_path)) {
            File::deleteDirectory($export->output_path);
        }
    }

    public function cancel(MyDumperExport $export, ?int $userId = null): void
    {
        if (! $export->isRunning()) {
            throw new MyDumperException(
                message: 'Export is not running.',
                userMessage: 'Export tidak sedang berjalan.',
            );
        }

        $this->runner->cancel($export);

        $export->update([
            'status' => MyDumperExportStatus::Cancelled,
            'current_stage' => MyDumperExportStage::Finished,
            'finished_at' => now(),
            'cancelled_at' => now(),
            'cancelled_by' => $userId,
            'message' => 'Export dibatalkan.',
        ]);

        $this->logService->append($export, 'Export cancelled by user.', 'warning', 'system');
        event(new ExportCancelled($export->fresh()));
    }

    public function retry(MyDumperExport $export, ?int $userId = null): MyDumperExport
    {
        if (! in_array($export->status, [MyDumperExportStatus::Failed, MyDumperExportStatus::Cancelled], true)) {
            throw new MyDumperException(
                message: 'Export cannot be retried.',
                userMessage: 'Export ini tidak dapat di-retry.',
            );
        }

        $profile = $export->profile;

        if ($profile === null) {
            $newExport = $this->repository->create([
                'profile_id' => null,
                'connection_id' => $export->connection_id,
                'storage_destination_id' => $export->storage_destination_id,
                'name' => $export->name.' (Retry)',
                'database' => $export->database,
                'type' => $export->type,
                'status' => MyDumperExportStatus::Waiting,
                'thread' => $export->thread,
                'compression' => $export->compression,
                'options_snapshot' => $export->options_snapshot,
                'created_by' => $userId,
            ]);
        } else {
            $newExport = $this->exportService->createExportFromProfile($profile, $userId);
        }

        $this->dispatchChain($newExport);

        return $newExport;
    }

    private function markRunning(MyDumperExport $export, MyDumperExportStage $stage): void
    {
        $export->update([
            'status' => MyDumperExportStatus::Running,
            'current_stage' => $stage,
            'progress_percent' => $stage->progressPercent(),
        ]);
    }

    private function markStage(MyDumperExport $export, MyDumperExportStage $stage, int $progressPercent): void
    {
        $export->update([
            'current_stage' => $stage,
            'progress_percent' => max($export->progress_percent, $progressPercent),
        ]);
    }

    private function markSuccess(MyDumperExport $export): void
    {
        $export->update([
            'status' => MyDumperExportStatus::Success,
            'current_stage' => MyDumperExportStage::Finished,
            'progress_percent' => 100,
            'finished_at' => now(),
            'message' => 'Export berhasil.',
        ]);

        event(new ExportCompleted($export->fresh()));
    }

    private function markFailed(MyDumperExport $export, Throwable $exception): void
    {
        $message = $exception instanceof MyDumperException
            ? $exception->userMessage()
            : 'Export gagal. Periksa log untuk detail.';

        $export->update([
            'status' => MyDumperExportStatus::Failed,
            'current_stage' => MyDumperExportStage::Finished,
            'finished_at' => now(),
            'message' => $message,
        ]);

        $this->logService->append($export, $message, 'error', 'system');
        event(new ExportFailed($export->fresh(), $exception));
    }
}
