<?php

namespace App\Services\Storage\Drivers;

use App\DTO\StorageTestResult;
use App\Enums\StorageDriver;

class S3StorageDriver extends AbstractStorageDriver
{
    public function driver(): StorageDriver
    {
        return StorageDriver::S3;
    }

    public function validateConfig(array $config): void
    {
        $this->requireNonEmptyString($config, 'key');
        $this->requireNonEmptyString($config, 'secret');
        $this->requireNonEmptyString($config, 'region');
        $this->requireNonEmptyString($config, 'bucket');
    }

    public function test(array $config): StorageTestResult
    {
        $this->validateConfig($config);

        $disk = $this->temporaryDisk($this->toFilesystemConfig($config));
        $result = $this->performWriteTest($disk);

        if (! $result->success) {
            return $result;
        }

        return new StorageTestResult(
            success: true,
            status: 'Connected',
            bucket: $config['bucket'],
            region: $config['region'],
            endpoint: $config['endpoint'] ?? null,
        );
    }

    public function toFilesystemConfig(array $config): array
    {
        $filesystemConfig = [
            'driver' => 's3',
            'key' => $config['key'],
            'secret' => $config['secret'],
            'region' => $config['region'],
            'bucket' => $config['bucket'],
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
            'throw' => true,
        ];

        if (! empty($config['endpoint'])) {
            $filesystemConfig['endpoint'] = $config['endpoint'];
        }

        if (! empty($config['prefix'])) {
            $filesystemConfig['root'] = trim((string) $config['prefix'], '/');
        }

        return $filesystemConfig;
    }
}
