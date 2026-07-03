<?php

namespace App\Services\Backup;

use App\Enums\StorageDriver;
use App\Exceptions\BackupHistoryException;
use App\Models\BackupDestination;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Services\BaseService;
use App\Services\Storage\Drivers\LocalStorageDriver;
use App\Services\Storage\StorageDriverManager;
use FilesystemIterator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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

        $filename = $location['filename'] ?? basename($location['path']);

        if (isset($location['absolute_path'])) {
            return response()->streamDownload(
                function () use ($location): void {
                    $stream = fopen($location['absolute_path'], 'rb');

                    if (! is_resource($stream)) {
                        throw BackupHistoryException::fileNotFound();
                    }

                    fpassthru($stream);
                    fclose($stream);
                },
                $filename,
            );
        }

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

        if (isset($location['absolute_path'])) {
            @unlink($location['absolute_path']);

            return;
        }

        $location['disk']->delete($location['path']);
    }

    /**
     * @return array{disk: Filesystem, path: string, destination_id: int, storage_root: ?string}|null
     */
    public function findLatestBackupFile(BackupProfile $profile): ?array
    {
        $profile->loadMissing('destinations');

        foreach ($profile->destinations->where('is_active', true) as $destination) {
            $disk = $this->diskForDestination($destination, $profile->id);
            $latestPath = $this->latestZipPathOnDisk($disk, $profile->uuid);

            if ($latestPath !== null) {
                return [
                    'disk' => $disk,
                    'path' => $latestPath,
                    'destination_id' => $destination->id,
                    'storage_root' => $this->storageRootFor($destination),
                ];
            }
        }

        return null;
    }

    /**
     * @return array{disk?: Filesystem, path?: string, absolute_path?: string, filename?: string}|null
     */
    public function resolveFileLocation(BackupHistory $history): ?array
    {
        $history->loadMissing('backupProfile.destinations');

        $profile = $history->backupProfile;

        if ($profile === null) {
            return null;
        }

        if ($location = $this->tryMetadataStorageRoot($history)) {
            return $location;
        }

        $storedPath = $this->normalizeStoragePath($history->metadata['storage_path'] ?? '');

        if ($storedPath !== '') {
            foreach ($this->destinationsToSearch($history, $profile) as $destination) {
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

        foreach ($this->destinationsToSearch($history, $profile) as $destination) {
            $disk = $this->diskForDestination($destination, $profile->id);
            $matched = $this->findPathByFilename($disk, $filename, $profile->uuid);

            if ($matched !== null) {
                return ['disk' => $disk, 'path' => $matched];
            }
        }

        return $this->scanLocalBackupsDirectory($filename, $profile->uuid);
    }

    /**
     * @return Collection<int, BackupDestination>
     */
    private function destinationsToSearch(BackupHistory $history, BackupProfile $profile): Collection
    {
        $destinations = $profile->destinations;

        $metadataDestinationId = $history->metadata['destination_id'] ?? null;

        if (! is_numeric($metadataDestinationId)) {
            return $destinations;
        }

        $storedDestination = BackupDestination::withTrashed()->find((int) $metadataDestinationId);

        if ($storedDestination === null || $destinations->contains('id', $storedDestination->id)) {
            return $destinations;
        }

        return collect([$storedDestination])->merge($destinations);
    }

    /**
     * @return array{absolute_path: string, filename: string}|null
     */
    private function tryMetadataStorageRoot(BackupHistory $history): ?array
    {
        $metadata = $history->metadata ?? [];
        $storageRoot = $metadata['storage_root'] ?? null;
        $storagePath = $this->normalizeStoragePath($metadata['storage_path'] ?? '');

        if (! is_string($storageRoot) || trim($storageRoot) === '' || $storagePath === '') {
            return null;
        }

        $absolutePath = $this->absolutePath($storageRoot, $storagePath);

        if (! is_file($absolutePath)) {
            return null;
        }

        return [
            'absolute_path' => $absolutePath,
            'filename' => basename($storagePath),
        ];
    }

    /**
     * @return array{absolute_path: string, filename: string}|null
     */
    private function scanLocalBackupsDirectory(string $filename, string $profileUuid): ?array
    {
        $backupsRoot = storage_path('app/backups');

        if (! is_dir($backupsRoot)) {
            return null;
        }

        $normalizedFilename = trim($filename);
        $normalizedUuid = trim($profileUuid, '/');
        $matches = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backupsRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $base = $file->getFilename();

            if (! str_ends_with(strtolower($path), '.zip')) {
                continue;
            }

            if ($base === $normalizedFilename || str_ends_with($path, '/'.$normalizedUuid.'/'.$normalizedFilename)) {
                $matches[] = $path;
            }
        }

        if ($matches === []) {
            return null;
        }

        sort($matches);

        return [
            'absolute_path' => end($matches),
            'filename' => $normalizedFilename,
        ];
    }

    private function findPathByFilename(Filesystem $disk, string $filename, string $profileUuid): ?string
    {
        $filename = trim($filename);

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

        if ($paths->isNotEmpty()) {
            return $paths->last();
        }

        return collect($disk->allFiles())
            ->filter(fn (string $path) => str_ends_with(strtolower($path), '.zip'))
            ->sort()
            ->last();
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

    private function storageRootFor(BackupDestination $destination): ?string
    {
        if ($destination->driver !== StorageDriver::Local) {
            return null;
        }

        try {
            return app(LocalStorageDriver::class)->resolveRootPath($destination->config ?? []);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeStoragePath(mixed $path): string
    {
        if (! is_string($path)) {
            return '';
        }

        return str_replace('\\', '/', trim($path));
    }

    private function absolutePath(string $root, string $relativePath): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        return $root.'/'.$relativePath;
    }
}
