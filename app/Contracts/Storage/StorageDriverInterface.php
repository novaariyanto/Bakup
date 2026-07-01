<?php

namespace App\Contracts\Storage;

use App\DTO\StorageTestResult;
use App\Enums\StorageDriver;

interface StorageDriverInterface
{
    public function driver(): StorageDriver;

    /**
     * @param  array<string, mixed>  $config
     */
    public function validateConfig(array $config): void;

    /**
     * @param  array<string, mixed>  $config
     */
    public function test(array $config): StorageTestResult;

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function toFilesystemConfig(array $config): array;
}
