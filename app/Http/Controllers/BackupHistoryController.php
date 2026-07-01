<?php

namespace App\Http\Controllers;

use App\Enums\BackupHistoryStatus;
use App\Exceptions\BackupExecutionException;
use App\Exceptions\BackupHistoryException;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Repositories\BackupHistoryRepository;
use App\Services\Backup\BackupHistoryManagementService;
use App\Services\Backup\BackupProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BackupHistoryController extends Controller
{
    public function index(Request $request, BackupHistoryRepository $repository): View
    {
        $this->authorize('viewAny', BackupHistory::class);

        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();
        $profile = $request->string('profile')->toString();
        $detailId = $request->integer('history') ?: null;

        $progressData = null;
        $detailHistory = null;

        if ($detailId) {
            $detailHistory = BackupHistory::with('logs')->find($detailId);

            if ($detailHistory) {
                $this->authorize('view', $detailHistory);
                $progressData = app(BackupProgressService::class)->forHistory($detailHistory);
            }
        }

        return view('backup-history.index', [
            'histories' => $repository->paginate(
                search: $search !== '' ? $search : null,
                status: $status === 'all' || $status === '' ? null : $status,
                profileId: $profile === 'all' || $profile === '' ? null : (int) $profile,
            ),
            'profiles' => BackupProfile::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => BackupHistoryStatus::cases(),
            'search' => $search,
            'statusFilter' => $status !== '' ? $status : 'all',
            'profileFilter' => $profile !== '' ? $profile : 'all',
            'detailHistory' => $detailHistory,
            'progressData' => $progressData,
            'showDetailModal' => $detailHistory !== null,
        ]);
    }

    public function show(BackupHistory $history, BackupProgressService $progressService): View
    {
        $this->authorize('view', $history);

        $history->loadMissing('logs');

        return view('backup-history.show', [
            'history' => $history,
            'progressData' => $progressService->forHistory($history),
        ]);
    }

    public function progress(BackupHistory $history, BackupProgressService $progressService): JsonResponse
    {
        $this->authorize('view', $history);

        $history->loadMissing('logs');

        return response()->json($progressService->forHistory($history));
    }

    public function destroy(BackupHistory $history, BackupHistoryManagementService $service): RedirectResponse
    {
        $this->authorize('delete', $history);

        try {
            $service->delete($history);

            return redirect()
                ->route('backup-history.index')
                ->with('success', 'Riwayat backup berhasil dihapus.');
        } catch (BackupHistoryException $exception) {
            return redirect()
                ->route('backup-history.index')
                ->with('error', $exception->userMessage());
        }
    }

    public function retry(
        BackupHistory $history,
        BackupHistoryManagementService $service,
        BackupProgressService $progressService,
    ): RedirectResponse {
        $history->loadMissing('backupProfile');
        $this->authorize('retry', $history);

        try {
            $newHistory = $service->retry($history, (int) auth()->id());
            $newHistory->loadMissing('logs');
            $progressData = $progressService->forHistory($newHistory);

            return redirect()
                ->route('backup-history.index', ['history' => $newHistory->id])
                ->with('success', 'Backup di-retry dan sedang diproses.')
                ->with('progress_data', $progressData);
        } catch (BackupExecutionException|BackupHistoryException $exception) {
            return back()->with('error', $exception->userMessage());
        }
    }
}
