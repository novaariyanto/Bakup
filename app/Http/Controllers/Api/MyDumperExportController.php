<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MyDumper\MyDumperException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MyDumperExportProfileRequest;
use App\Http\Resources\MyDumperExportResource;
use App\Models\MyDumperExport;
use App\Repositories\MyDumperExportRepository;
use App\Services\MyDumper\MyDumperExecutionService;
use App\Services\MyDumper\MyDumperExportService;
use App\Services\MyDumper\MyDumperLogService;
use App\Services\MyDumper\MyDumperProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyDumperExportController extends Controller
{
    public function index(Request $request, MyDumperExportRepository $repository): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MyDumperExport::class);

        $exports = $repository->paginate(
            search: $request->string('q')->trim()->toString() ?: null,
            status: $request->string('status')->toString() ?: null,
            connectionId: $request->integer('connection') ?: null,
        );

        return MyDumperExportResource::collection($exports);
    }

    public function store(
        MyDumperExportProfileRequest $request,
        MyDumperExportService $exportService,
        MyDumperExecutionService $executionService,
    ): JsonResponse {
        $this->authorize('create', MyDumperExport::class);

        try {
            $profile = $exportService->createProfile($request->toServicePayload(), (int) auth()->id());
            $export = $executionService->dispatchFromProfile($profile, (int) auth()->id());

            return (new MyDumperExportResource($export))
                ->response()
                ->setStatusCode(201);
        } catch (MyDumperException $exception) {
            return response()->json(['message' => $exception->userMessage()], 422);
        }
    }

    public function show(MyDumperExport $export): MyDumperExportResource
    {
        $this->authorize('view', $export);

        $export->load(['connection', 'profile', 'storageDestination', 'files']);

        return new MyDumperExportResource($export);
    }

    public function run(
        MyDumperExport $export,
        MyDumperExecutionService $executionService,
    ): JsonResponse {
        $this->authorize('run', $export);

        if ($export->profile === null) {
            return response()->json(['message' => 'Export tidak memiliki profile.'], 422);
        }

        try {
            $newExport = $executionService->dispatchFromProfile($export->profile, (int) auth()->id());

            return response()->json(new MyDumperExportResource($newExport));
        } catch (MyDumperException $exception) {
            return response()->json(['message' => $exception->userMessage()], 422);
        }
    }

    public function cancel(MyDumperExport $export, MyDumperExecutionService $executionService): JsonResponse
    {
        $this->authorize('cancel', $export);

        try {
            $executionService->cancel($export, (int) auth()->id());

            return response()->json(new MyDumperExportResource($export->fresh()));
        } catch (MyDumperException $exception) {
            return response()->json(['message' => $exception->userMessage()], 422);
        }
    }

    public function retry(MyDumperExport $export, MyDumperExecutionService $executionService): JsonResponse
    {
        $this->authorize('retry', $export);

        try {
            $newExport = $executionService->retry($export, (int) auth()->id());

            return response()->json(new MyDumperExportResource($newExport));
        } catch (MyDumperException $exception) {
            return response()->json(['message' => $exception->userMessage()], 422);
        }
    }

    public function destroy(MyDumperExport $export, MyDumperExportService $exportService): JsonResponse
    {
        $this->authorize('delete', $export);

        $exportService->deleteExport($export);

        return response()->json(['message' => 'Export deleted.']);
    }

    public function logs(MyDumperExport $export, Request $request, MyDumperLogService $logService): JsonResponse
    {
        $this->authorize('view', $export);

        return response()->json([
            'logs' => $export->logs()->when(
                $request->filled('q'),
                fn ($query) => $query->where('message', 'like', '%'.$request->string('q').'%')
            )->limit(500)->get(),
        ]);
    }

    public function download(MyDumperExport $export): StreamedResponse|JsonResponse
    {
        $this->authorize('download', $export);

        if ($export->log_path && file_exists($export->log_path)) {
            return response()->streamDownload(
                fn () => readfile($export->log_path),
                'export-'.$export->uuid.'.log',
            );
        }

        return response()->json(['message' => 'File tidak ditemukan.'], 404);
    }

    public function progress(MyDumperExport $export, MyDumperProgressService $progressService): JsonResponse
    {
        $this->authorize('view', $export);

        return response()->json($progressService->forExport($export->fresh()));
    }
}
