<?php

namespace App\Services\Storage\Drivers;

use App\Contracts\Storage\StorageDriverInterface;
use App\DTO\StorageTestResult;
use App\Enums\StorageDriver;
use App\Exceptions\StorageDestinationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

abstract class AbstractStorageDriver implements StorageDriverInterface
{
    protected function temporaryDisk(array $filesystemConfig): Filesystem
    {
        $diskName = 'backup-test-'.Str::random(12);

        config(["filesystems.disks.{$diskName}" => $filesystemConfig]);

        return Storage::disk($diskName);
    }

    protected function performWriteTest(Filesystem $disk): StorageTestResult
    {
        $testFile = '.backup-manager-test-'.Str::random(8);

        try {
            $disk->put($testFile, 'backup-manager-connection-test');
            $disk->delete($testFile);

            return new StorageTestResult(
                success: true,
                status: 'Writable',
            );
        } catch (Throwable $exception) {
            return StorageTestResult::failed($exception->getMessage());
        }
    }

    protected function requireNonEmptyString(array $config, string $key): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw StorageDestinationException::invalidConfig("Field {$key} wajib diisi.");
        }

        return $value;
    }
}
