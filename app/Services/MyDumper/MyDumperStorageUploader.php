<?php

namespace App\Services\MyDumper;

use App\Exceptions\MyDumper\MyDumperException;
use App\Models\MyDumperExport;
use App\Services\BaseService;
use App\Services\Storage\StorageDriverManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MyDumperStorageUploader extends BaseService
{
    public function __construct(
        private readonly StorageDriverManager $storageDriverManager,
        private readonly MyDumperLogService $logService,
    ) {}

    public function upload(MyDumperExport $export): string
    {
        $destination = $export->storageDestination;

        if ($destination === null || ! $destination->is_active) {
            throw MyDumperException::destinationInactive();
        }

        $outputPath = $export->output_path;

        if ($outputPath === null || ! File::isDirectory($outputPath)) {
            throw MyDumperException::uploadFailed('Folder export tidak ditemukan.');
        }

        $diskName = 'mydumper_upload_'.$export->id;
        $driver = $this->storageDriverManager->driver($destination->driver);
        config(['filesystems.disks.'.$diskName => $driver->toFilesystemConfig($destination->config ?? [])]);

        $remotePrefix = 'mydumper/'.$export->uuid;
        $disk = Storage::disk($diskName);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($outputPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = ltrim(str_replace($outputPath, '', $file->getPathname()), DIRECTORY_SEPARATOR.'/\\');
            $remotePath = $remotePrefix.'/'.str_replace('\\', '/', $relativePath);

            try {
                $disk->put($remotePath, fopen($file->getPathname(), 'rb'));
            } catch (\Throwable $exception) {
                throw MyDumperException::uploadFailed($exception->getMessage());
            }
        }

        $metadata = $export->metadata ?? [];
        $metadata['storage_path'] = $remotePrefix;
        $metadata['destination_id'] = $destination->id;
        $metadata['destination_driver'] = $destination->driver->value;

        $export->update(['metadata' => $metadata]);

        $this->logService->append($export, 'Upload to storage completed: '.$remotePrefix, 'info', 'system');

        return $remotePrefix;
    }
}
