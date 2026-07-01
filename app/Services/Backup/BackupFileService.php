<?php

namespace App\Services\Backup;

use App\Exceptions\BackupHistoryException;
use App\Models\BackupDestination;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Services\BaseService;
use App\Services\Storage\StorageDriverManager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupFileService extends BaseService
{
    public function __construct(
        private readonly StorageDriverManager $storageDriverManager,
        private readonly BackupRuntimeConfigService $runtimeConfigService,
    ) {}

    public function downloadResponse(BackupHistory $history): StreamedResponse
    {
        $location = $this->resolveFileLocation($history);

        if ($location === null) {
            throw BackupHistoryException::fileNotFound();
        }

        $filename = basename($location['path']);

        return response()->streamDownload(
            function () use ($location): void {
                $stream = $location['disk']->readStream($location['path']);

                if (! is_resource($stream)) {
                    throw BackupHistoryException::fileNotFound();
                }

                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            $filename,
        );
    }

    public function deleteFiles(BackupHistory $history): void
    {
        $location = $this->resolveFileLocation($history);

        if ($location === null) {
            return;
        }

        $location['disk']->delete($location['path']);
    }

    /**
     * @return array{disk: Filesystem, path: string}|null
     */
    public function findLatestBackupFile(BackupProfile $profile): ?array
    {
        $profile->loadMissing('destinations');

        foreach ($profile->destinations->where('is_active', true) as $destination) {
            $disk = $this->diskForDestination($destination, $profile->id);
            $latestPath = $this->latestZipPathOnDisk($disk, $profile->uuid);

            if ($latestPath !== null) {
                return ['disk' => $disk, 'path' => $latestPath];
            }
        }

        return null;
    }

    /**
     * @return array{disk: Filesystem, path: string}|null
     */
    public function resolveFileLocation(BackupHistory $history): ?array
    {
        $history->loadMissing('backupProfile.destinations');

        $profile = $history->backupProfile;

        if ($profile === null) {
            return null;
        }

        $storedPath = $history->metadata['storage_path'] ?? null;

        if (is_string($storedPath) && $storedPath !== '') {
            foreach ($profile->destinations as $destination) {
                $disk = $this->diskForDestination($destination, $profile->id);

                if ($disk->exists($storedPath)) {
                    return ['disk' => $disk, 'path' => $storedPath];
                }
            }
        }

        $filename = $history->filename;

        if (! filled($filename)) {
            return $this->findLatestBackupFile($profile);
        }

        foreach ($profile->destinations as $destination) {
            $disk = $this->diskForDestination($destination, $profile->id);
            $matched = $this->findPathByFilename($disk, $filename, $profile->uuid);

            if ($matched !== null) {
                return ['disk' => $disk, 'path' => $matched];
            }
        }

        return null;
    }

    private function findPathByFilename(Filesystem $disk, string $filename, string $profileUuid): ?string
    {
        if ($disk->exists($filename)) {
            return $filename;
        }

        $profileScopedPath = trim($profileUuid, '/').'/'.$filename;

        if ($disk->exists($profileScopedPath)) {
            return $profileScopedPath;
        }

        $matched = collect($disk->allFiles())
            ->first(fn (string $path) => $path === $filename || str_ends_with($path, '/'.$filename));

        if ($matched !== null) {
            return $matched;
        }

        $prefix = Str::beforeLast($filename, '.').'-';
        $prefixed = collect($disk->allFiles())
            ->filter(fn (string $path) => str_starts_with(basename($path), $prefix) && str_ends_with(strtolower($path), '.zip'))
            ->sort()
            ->last();

        if ($prefixed !== null) {
            return $prefixed;
        }

        return $this->latestZipPathOnDisk($disk, $profileUuid);
    }

    private function latestZipPathOnDisk(Filesystem $disk, string $profileUuid): ?string
    {
        $paths = collect($disk->allFiles(trim($profileUuid, '/')))
            ->filter(fn (string $path) => str_ends_with(strtolower($path), '.zip'))
            ->sort()
            ->values();

        return $paths->last();
    }

    private function diskForDestination(BackupDestination $destination, int $profileId): Filesystem
    {
        $diskName = $this->runtimeConfigService->destinationDiskName($profileId, $destination->id);

        config([
            'filesystems.disks.'.$diskName => $this->storageDriverManager
                ->driver($destination->driver)
                ->toFilesystemConfig($destination->config ?? []),
        ]);

        return Storage::disk($diskName);
    }
}
