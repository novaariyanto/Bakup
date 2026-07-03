<?php

namespace App\Services\Storage\Drivers;

use App\DTO\StorageTestResult;
use App\Enums\StorageDriver;
use App\Exceptions\StorageDestinationException;

class LocalStorageDriver extends AbstractStorageDriver
{
    public function driver(): StorageDriver
    {
        return StorageDriver::Local;
    }

    public function validateConfig(array $config): void
    {
        $this->requireNonEmptyString($config, 'path');
        $this->resolveRoot($config);
    }

    public function test(array $config): StorageTestResult
    {
        $this->validateConfig($config);

        $root = $this->resolveRoot($config);

        if (! is_dir($root) && ! mkdir($root, 0755, true) && ! is_dir($root)) {
            return StorageTestResult::failed("Tidak dapat membuat direktori: {$root}");
        }

        $disk = $this->temporaryDisk($this->toFilesystemConfig($config));
        $result = $this->performWriteTest($disk);

        if (! $result->success) {
            return $result;
        }

        return new StorageTestResult(
            success: true,
            status: 'Writable',
            resolvedPath: $root,
        );
    }

    public function toFilesystemConfig(array $config): array
    {
        return [
            'driver' => 'local',
            'root' => $this->resolveRootPath($config),
            'throw' => true,
        ];
    }

    public function resolveRootPath(array $config): string
    {
        return $this->resolveRoot($config);
    }

    private function resolveRoot(array $config): string
    {
        $path = trim((string) ($config['path'] ?? ''));

        if ($path === '') {
            throw StorageDestinationException::invalidConfig('Path penyimpanan wajib diisi.');
        }

        if ($this->isAbsolutePath($path)) {
            $root = $this->normalizePath($path);

            if (! $this->isWithinStoragePath($root)) {
                throw StorageDestinationException::invalidConfig(
                    'Path absolut harus berada di dalam direktori storage aplikasi.'
                );
            }

            return $root;
        }

        $relative = trim($path, '/\\');

        return storage_path('app/backups/'.$relative);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        if (preg_match('/^[A-Za-z]:\\//', $normalized)) {
            return $normalized;
        }

        return rtrim($normalized, '/');
    }

    private function isWithinStoragePath(string $path): bool
    {
        $storageRoot = str_replace('\\', '/', realpath(storage_path()) ?: storage_path());
        $target = str_replace('\\', '/', realpath($path) ?: $path);

        return str_starts_with($target, $storageRoot);
    }
}
