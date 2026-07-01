<?php

namespace App\Services\Storage\Sftp;

use App\DTO\StorageTestResult;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SftpConnectionService
{
    /**
     * @param  array<string, mixed>  $filesystemConfig
     */
    public function performWriteTest(array $filesystemConfig): StorageTestResult
    {
        $disk = $this->temporaryDisk($filesystemConfig);
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

    /**
     * @param  array<string, mixed>  $filesystemConfig
     */
    private function temporaryDisk(array $filesystemConfig): Filesystem
    {
        $diskName = 'sftp-test-'.Str::random(12);

        config(["filesystems.disks.{$diskName}" => $filesystemConfig]);

        return Storage::disk($diskName);
    }
}
