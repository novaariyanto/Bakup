<?php

namespace App\Http\Controllers;

use App\Enums\MyDumper\MyDumperExportType;
use App\Enums\ScheduleType;
use App\Exceptions\MyDumper\MyDumperException;
use App\Http\Requests\MyDumperExportProfileRequest;
use App\Models\BackupDestination;
use App\Models\DatabaseConnection;
use App\Models\MyDumperExport;
use App\Repositories\MyDumperExportRepository;
use App\Services\Backup\BackupProfileService;
use App\Services\MyDumper\MyDumperCommandBuilder;
use App\Services\MyDumper\MyDumperExecutionService;
use App\Services\MyDumper\MyDumperExportService;
use App\Services\MyDumper\MyDumperLogService;
use App\Services\MyDumper\MyDumperPreflightValidator;
use App\Services\MyDumper\MyDumperProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyDumperExportController extends Controller
{
    public function index(Request $request, MyDumperExportRepository $repository): View
    {
        $this->authorize('viewAny', MyDumperExport::class);

        return view('mydumper-exports.index', [
            'exports' => $repository->paginate(
                search: $request->string('q')->trim()->toString() ?: null,
                status: $request->string('status')->toString() ?: null,
                connectionId: $request->integer('connection') ?: null,
                profileId: $request->integer('profile') ?: null,
                sort: $request->string('sort')->toString() ?: 'created_at',
                direction: $request->string('direction')->toString() ?: 'desc',
            ),
            'connections' => DatabaseConnection::query()->where('is_active', true)->orderBy('name')->get(),
            'search' => $request->string('q')->trim()->toString(),
            'statusFilter' => $request->string('status')->toString() ?: 'all',
            'connectionFilter' => $request->string('connection')->toString() ?: 'all',
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MyDumperExport::class);

        return view('mydumper-exports.create', [
            'connections' => DatabaseConnection::query()->where('is_active', true)->orderBy('name')->get(),
            'destinations' => BackupDestination::query()->where('is_active', true)->orderBy('name')->get(),
            'exportTypes' => MyDumperExportType::cases(),
            'scheduleTypes' => ScheduleType::cases(),
            'defaults' => [
                'threads' => config('mydumper.default_threads', 4),
                'compression' => false,
                'schedule_type' => ScheduleType::Manual->value,
                'export_type' => MyDumperExportType::Full->value,
                'options' => ['build_metadata' => true, 'lock_mode' => 'auto'],
            ],
        ]);
    }

    public function store(
        MyDumperExportProfileRequest $request,
        MyDumperExportService $exportService,
        MyDumperExecutionService $executionService,
    ): RedirectResponse {
        $this->authorize('create', MyDumperExport::class);

        try {
            $profile = $exportService->createProfile($request->toServicePayload(), (int) auth()->id());

            if ($request->boolean('run_immediately')) {
                $export = $executionService->dispatchFromProfile($profile, (int) auth()->id());

                return redirect()
                    ->route('mydumper-exports.show', $export)
                    ->with('success', 'Export berhasil dibuat dan sedang diproses.');
            }

            return redirect()
                ->route('mydumper-exports.index')
                ->with('success', 'Export profile berhasil disimpan.');
        } catch (MyDumperException $exception) {
            return back()->withInput()->with('error', $exception->userMessage());
        }
    }

    public function show(MyDumperExport $export, MyDumperProgressService $progressService): View
    {
        $this->authorize('view', $export);

        $export->load(['profile', 'connection', 'storageDestination', 'files', 'logs']);

        return view('mydumper-exports.show', [
            'export' => $export,
            'progressData' => $progressService->forExport($export),
            'progressUrl' => route('mydumper-exports.progress', ['export' => $export->getKey()]),
        ]);
    }

    public function progress(MyDumperExport $export, MyDumperProgressService $progressService): JsonResponse
    {
        $this->authorize('view', $export);

        return response()->json($progressService->forExport($export->fresh()));
    }

    public function previewCommand(
        Request $request,
        MyDumperCommandBuilder $commandBuilder,
        MyDumperPreflightValidator $preflightValidator,
    ): JsonResponse {
        $this->authorize('create', MyDumperExport::class);

        $data = $request->isJson() ? $request->json()->all() : $request->all();

        $connection = DatabaseConnection::findOrFail((int) ($data['database_connection_id'] ?? 0));
        $database = $data['database'] ?? $connection->database_name;
        $exportType = MyDumperExportType::from($data['export_type'] ?? MyDumperExportType::Full->value);

        $preview = $commandBuilder->preview(
            connection: $connection,
            database: $database,
            outputDirectory: $preflightValidator->validateStagingDirectory('preview'),
            exportType: $exportType,
            threads: (int) ($data['threads'] ?? config('mydumper.default_threads', 4)),
            compression: filter_var($data['compression'] ?? false, FILTER_VALIDATE_BOOLEAN),
            options: \App\DTO\MyDumper\MyDumperExportOptions::fromArray($data['options'] ?? []),
            selectedTables: $data['selected_tables'] ?? null,
            excludeTables: $data['exclude_tables'] ?? null,
        );

        return response()->json(['command' => $preview]);
    }

    public function tables(DatabaseConnection $databaseConnection, BackupProfileService $service): JsonResponse
    {
        $this->authorize('create', MyDumperExport::class);

        try {
            $tables = $service->fetchTablesForConnection($databaseConnection);

            return response()->json([
                'tables' => array_map(fn ($table) => $table->toArray(), $tables),
            ]);
        } catch (\App\Exceptions\BackupManagerException $exception) {
            return response()->json(['message' => $exception->userMessage()], 422);
        }
    }

    public function cancel(MyDumperExport $export, MyDumperExecutionService $executionService): RedirectResponse
    {
        $this->authorize('cancel', $export);

        try {
            $executionService->cancel($export, (int) auth()->id());

            return back()->with('success', 'Export dibatalkan.');
        } catch (MyDumperException $exception) {
            return back()->with('error', $exception->userMessage());
        }
    }

    public function retry(MyDumperExport $export, MyDumperExecutionService $executionService): RedirectResponse
    {
        $this->authorize('retry', $export);

        try {
            $newExport = $executionService->retry($export, (int) auth()->id());

            return redirect()
                ->route('mydumper-exports.show', $newExport)
                ->with('success', 'Export di-retry dan sedang diproses.');
        } catch (MyDumperException $exception) {
            return back()->with('error', $exception->userMessage());
        }
    }

    public function destroy(MyDumperExport $export, MyDumperExportService $exportService): RedirectResponse
    {
        $this->authorize('delete', $export);

        $exportService->deleteExport($export);

        return redirect()
            ->route('mydumper-exports.index')
            ->with('success', 'Export berhasil dihapus.');
    }

    public function bulk(Request $request, MyDumperExportService $exportService, MyDumperExecutionService $executionService): RedirectResponse
    {
        $this->authorize('viewAny', MyDumperExport::class);

        $action = $request->string('action')->toString();
        $ids = array_map('intval', (array) $request->input('ids', []));

        if ($ids === []) {
            return back()->with('error', 'Pilih minimal satu export.');
        }

        if ($action === 'delete') {
            $deleted = $exportService->bulkDelete($ids);

            return back()->with('success', "{$deleted} export berhasil dihapus.");
        }

        if ($action === 'retry') {
            $count = 0;

            foreach (MyDumperExport::query()->whereIn('id', $ids)->get() as $export) {
                if (auth()->user()->can('retry', $export)) {
                    $executionService->retry($export, (int) auth()->id());
                    $count++;
                }
            }

            return back()->with('success', "{$count} export di-retry.");
        }

        return back()->with('error', 'Aksi bulk tidak valid.');
    }

    public function downloadLog(Request $request, MyDumperExport $export, MyDumperLogService $logService): StreamedResponse
    {
        $this->authorize('view', $export);

        $content = $logService->read($export, $request->string('q')->toString() ?: null);

        return response()->streamDownload(
            fn () => print ($content),
            'mydumper-export-'.$export->uuid.'.log',
            ['Content-Type' => 'text/plain'],
        );
    }

    public function downloadMetadata(MyDumperExport $export): StreamedResponse|RedirectResponse
    {
        $this->authorize('download', $export);

        if ($export->metadata_path && File::exists($export->metadata_path)) {
            return response()->streamDownload(
                fn () => readfile($export->metadata_path),
                'metadata-'.$export->uuid.'.json',
            );
        }

        return back()->with('error', 'File metadata tidak ditemukan.');
    }

    public function downloadFile(MyDumperExport $export, int $fileId): StreamedResponse|RedirectResponse
    {
        $this->authorize('download', $export);

        $file = $export->files()->findOrFail($fileId);
        $absolutePath = $export->output_path.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file->relative_path);

        if ($export->output_path && File::exists($absolutePath)) {
            return response()->streamDownload(
                fn () => readfile($absolutePath),
                basename($file->relative_path),
            );
        }

        return back()->with('error', 'File tidak ditemukan di staging.');
    }
}
