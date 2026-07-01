<?php

namespace App\Http\Controllers;

use App\Exceptions\BackupHistoryException;
use App\Models\BackupHistory;
use App\Services\Backup\BackupFileService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupHistoryDownloadController extends Controller
{
    public function __invoke(BackupHistory $history, BackupFileService $fileService): StreamedResponse|RedirectResponse
    {
        $this->authorize('download', $history);

        try {
            return $fileService->downloadResponse($history);
        } catch (BackupHistoryException $exception) {
            return redirect()
                ->route('backup-history.index')
                ->with('error', $exception->userMessage());
        }
    }
}
