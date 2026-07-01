<?php

namespace App\Services\Storage\Drivers;

use App\DTO\StorageTestResult;
use App\Enums\StorageDriver;
use App\Services\Storage\Sftp\SftpAuthenticationResolver;
use App\Services\Storage\Sftp\SftpConfigurationValidator;
use App\Services\Storage\Sftp\SftpTestConnectionAction;

class SftpStorageDriver extends AbstractStorageDriver
{
    public function __construct(
        private readonly SftpConfigurationValidator $configurationValidator,
        private readonly SftpAuthenticationResolver $authenticationResolver,
        private readonly SftpTestConnectionAction $testConnectionAction,
    ) {}

    public function driver(): StorageDriver
    {
        return StorageDriver::Sftp;
    }

    public function validateConfig(array $config): void
    {
        $this->configurationValidator->validate($config);
    }

    public function test(array $config): StorageTestResult
    {
        return $this->testConnectionAction->execute($config);
    }

    public function toFilesystemConfig(array $config): array
    {
        return $this->authenticationResolver->buildFlysystemConfig($config);
    }
}
