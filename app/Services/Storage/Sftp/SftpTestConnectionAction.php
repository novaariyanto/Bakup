<?php

namespace App\Services\Storage\Sftp;

use App\DTO\StorageTestResult;
use App\Enums\SftpAuthenticationMethod;
use App\Exceptions\StorageDestinationException;
use App\Support\BackupLogger;
use Throwable;

class SftpTestConnectionAction
{
    public function __construct(
        private readonly SftpConfigurationValidator $validator,
        private readonly SftpAuthenticationResolver $authenticationResolver,
        private readonly SftpConnectionService $connectionService,
        private readonly SftpErrorMapper $errorMapper,
        private readonly BackupLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function execute(array $config): StorageTestResult
    {
        $method = $this->authenticationResolver->resolveMethod($config);

        try {
            $this->validator->validate($config);

            $filesystemConfig = $this->authenticationResolver->buildFlysystemConfig($config);
            $writeResult = $this->connectionService->performWriteTest($filesystemConfig);

            if (! $writeResult->success) {
                $message = $this->errorMapper->map(new \RuntimeException($writeResult->errorMessage ?? 'Write test failed'));
                $this->logFailure($config, $method, $message, new \RuntimeException($writeResult->errorMessage ?? ''));

                return StorageTestResult::failed($message);
            }

            return new StorageTestResult(
                success: true,
                status: 'Connected',
                host: (string) ($config['host'] ?? ''),
                resolvedPath: (string) ($config['root'] ?? '/'),
            );
        } catch (StorageDestinationException $exception) {
            $this->logFailure($config, $method, $exception->userMessage(), $exception);

            return StorageTestResult::failed($exception->userMessage());
        } catch (Throwable $exception) {
            $message = $this->errorMapper->map($exception);
            $this->logFailure($config, $method, $message, $exception);

            return StorageTestResult::failed($message);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function logFailure(
        array $config,
        SftpAuthenticationMethod $method,
        string $userMessage,
        Throwable $exception,
    ): void {
        $this->logger->error('SFTP storage test failed', [
            'host' => $config['host'] ?? null,
            'port' => (int) ($config['port'] ?? 22),
            'username' => $config['username'] ?? null,
            'auth_method' => $method->value,
            'user_message' => $userMessage,
            'exception' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
