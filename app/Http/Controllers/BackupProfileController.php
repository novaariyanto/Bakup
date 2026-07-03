<?php

namespace App\Http\Controllers;

use App\Enums\CompressionType;
use App\Enums\RetentionType;
use App\Enums\ScheduleType;
use App\Exceptions\BackupExecutionException;
use App\Exceptions\BackupManagerException;
use App\Exceptions\BackupProfileException;
use App\Http\Requests\BackupProfileRequest;
use App\Models\BackupDestination;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Models\DatabaseConnection;
use App\Repositories\BackupProfileRepository;
use App\Services\Backup\BackupExecutionService;
use App\Services\Backup\BackupProfileService;
use App\Services\Backup\BackupProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BackupProfileController extends Controller
{
    public function index(Request $request, BackupProfileRepository $repository): View
    {
        $this->authorize('viewAny', BackupProfile::class);

        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();
        $connection = $request->string('connection')->toString();
        $progressHistoryId = $request->integer('progress') ?: session('progress_history_id') ?: null;
        $progressData = session('progress_data');

        if ($progressHistoryId) {
            $progressHistory = BackupHistory::with('logs')->find($progressHistoryId);

            if ($progressHistory) {
                $this->authorize('view', $progressHistory);
                $progressData = app(BackupProgressService::class)->forHistory($progressHistory);
            }
        }

        return view('backup-profiles.index', [
            'profiles' => $repository->paginate(
                search: $search !== '' ? $search : null,
                status: $status === 'all' || $status === '' ? null : $status,
                connectionId: $connection === 'all' || $connection === '' ? null : (int) $connection,
            ),
            'connections' => DatabaseConnection::query()->where('is_active', true)->orderBy('name')->get(),
            'destinations' => BackupDestination::query()->where('is_active', true)->orderBy('name')->get(),
            'compressionTypes' => CompressionType::cases(),
            'scheduleTypes' => ScheduleType::cases(),
            'retentionTypes' => RetentionType::cases(),
            'search' => $search,
            'statusFilter' => $status !== '' ? $status : 'all',
            'connectionFilter' => $connection !== '' ? $connection : 'all',
            'progressData' => $progressData,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BackupProfile::class);

        return view('backup-profiles.create', [
            'connections' => DatabaseConnection::query()->where('is_active', true)->orderBy('name')->get(),
            'destinations' => BackupDestination::query()->where('is_active', true)->orderBy('name')->get(),
            'compressionTypes' => CompressionType::cases(),
            'scheduleTypes' => ScheduleType::cases(),
            'retentionTypes' => RetentionType::cases(),
            'defaults' => [
                'backup_database' => true,
                'backup_folders' => false,
                'include_stored_procedures' => false,
                'include_views' => false,
                'compression' => CompressionType::Zip->value,
                'schedule_type' => ScheduleType::Manual->value,
                'schedule_time' => '02:00',
                'schedule_day_of_week' => 1,
                'schedule_day_of_month' => 1,
                'retention_type' => RetentionType::KeepLast->value,
                'retention_value' => 7,
                'is_active' => true,
                'selected_destination_ids' => [],
                'include_folders' => [],
                'exclude_folders' => [],
                'table_dump_modes' => [],
            ],
        ]);
    }

    public function store(BackupProfileRequest $request, BackupProfileService $service): RedirectResponse
    {
        $this->authorize('create', BackupProfile::class);

        try {
            $service->create($request->toServicePayload());

            return redirect()
                ->route('backup-profiles.index')
                ->with('success', 'Backup profile berhasil ditambahkan.');
        } catch (BackupProfileException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->userMessage());
        }
    }

    public function edit(BackupProfile $backupProfile): View
    {
        $this->authorize('update', $backupProfile);

        $profile = BackupProfile::with([
            'excludedTables',
            'includeFolders',
            'excludeFolders',
            'destinations',
        ])->findOrFail($backupProfile->id);

        return view('backup-profiles.edit', [
            'profile' => $profile,
            'connections' => DatabaseConnection::query()->where('is_active', true)->orderBy('name')->get(),
            'destinations' => BackupDestination::query()->where('is_active', true)->orderBy('name')->get(),
            'compressionTypes' => CompressionType::cases(),
            'scheduleTypes' => ScheduleType::cases(),
            'retentionTypes' => RetentionType::cases(),
            'formDefaults' => [
                'name' => $profile->name,
                'description' => $profile->description ?? '',
                'database_connection_id' => $profile->database_connection_id,
                'backup_database' => $profile->backup_database,
                'backup_folders' => $profile->backup_folders,
                'compression' => $profile->compression->value,
                'schedule_type' => $profile->schedule_type->value,
                'schedule_cron' => $profile->schedule_cron ?? '',
                'schedule_time' => $profile->schedule_time ? substr((string) $profile->schedule_time, 0, 5) : '02:00',
                'schedule_day_of_week' => $profile->schedule_day_of_week ?? 1,
                'schedule_day_of_month' => $profile->schedule_day_of_month ?? 1,
                'retention_type' => $profile->retention_type->value,
                'retention_value' => $profile->retention_value,
                'is_active' => $profile->is_active,
                'selected_destination_ids' => $profile->destinations->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'include_folders' => $profile->includeFolders->pluck('path')->all(),
                'exclude_folders' => $profile->excludeFolders->pluck('path')->all(),
                'table_dump_modes' => $profile->excludedTables
                    ->mapWithKeys(fn ($table) => [$table->table_name => $table->dump_mode->value])
                    ->all(),
            ],
        ]);
    }

    public function update(
        BackupProfileRequest $request,
        BackupProfile $backupProfile,
        BackupProfileService $service,
    ): RedirectResponse {
        $this->authorize('update', $backupProfile);

        try {
            $service->update($backupProfile, $request->toServicePayload());

            return redirect()
                ->route('backup-profiles.index')
                ->with('success', 'Backup profile berhasil diperbarui.');
        } catch (BackupProfileException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->userMessage());
        }
    }

    public function destroy(BackupProfile $backupProfile, BackupProfileService $service): RedirectResponse
    {
        $this->authorize('delete', $backupProfile);

        $service->delete($backupProfile);

        return redirect()
            ->route('backup-profiles.index')
            ->with('success', 'Backup profile berhasil dihapus.');
    }

    public function tables(DatabaseConnection $databaseConnection, BackupProfileService $service): JsonResponse
    {
        $this->authorize('viewAny', BackupProfile::class);

        try {
            $tables = $service->fetchTablesForConnection($databaseConnection);

            return response()->json([
                'tables' => array_map(fn ($table) => $table->toArray(), $tables),
            ]);
        } catch (BackupManagerException $exception) {
            return response()->json([
                'message' => $exception->userMessage(),
            ], 422);
        }
    }

    public function runBackup(
        BackupProfile $backupProfile,
        BackupExecutionService $executionService,
        BackupProgressService $progressService,
    ): RedirectResponse {
        try {
            $this->authorize('run', $backupProfile);

            $history = $executionService->dispatch($backupProfile, (int) auth()->id());
            $progressData = $progressService->forHistory($history->fresh(['logs']));

            return redirect()
                ->route('backup-profiles.index', ['progress' => $history->id])
                ->with('success', 'Backup dimulai dan sedang diproses di background.')
                ->with('progress_data', $progressData);
        } catch (BackupExecutionException $exception) {
            return back()->with('error', $exception->userMessage());
        }
    }

    public function progress(BackupHistory $history, BackupProgressService $progressService): JsonResponse
    {
        $this->authorize('view', $history);

        $history->loadMissing('logs');

        return response()->json($progressService->forHistory($history));
    }
}
