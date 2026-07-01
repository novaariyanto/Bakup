<?php

namespace App\Services\Storage;

use App\DTO\StorageTestResult;
use App\Enums\StorageDriver;
use App\Exceptions\StorageDestinationException;
use App\Models\BackupDestination;
use App\Repositories\BackupDestinationRepository;
use App\Services\BaseService;
use App\Services\Storage\Sftp\SftpAuthenticationResolver;
use App\Support\BackupLogger;
use Illuminate\Support\Facades\RateLimiter;

class StorageDestinationService extends BaseService
{
    private const TEST_RATE_LIMIT = 10;

    public function __construct(
        private readonly BackupDestinationRepository $repository,
        private readonly StorageDriverManager $driverManager,
        private readonly BackupLogger $logger,
    ) {}

    public function create(array $data): BackupDestination
    {
        $driver = $this->resolveDriver($data['driver'] ?? null);
        $config = $data['config'] ?? [];

        $this->driverManager->driver($driver)->validateConfig($config);

        $destination = $this->repository->create([
            'name' => $data['name'],
            'driver' => $driver,
            'config' => $config,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->logger->info('Storage destination created', [
            'destination_id' => $destination->id,
            'name' => $destination->name,
            'driver' => $driver->value,
        ]);

        return $destination;
    }

    public function update(BackupDestination $destination, array $data): BackupDestination
    {
        $driver = isset($data['driver'])
            ? $this->resolveDriver($data['driver'])
            : $destination->driver;

        $config = $this->mergeConfig(
            $driver,
            $destination->config ?? [],
            $data['config'] ?? [],
        );

        $this->driverManager->driver($driver)->validateConfig($config);

        $updated = $this->repository->update($destination, [
            'name' => $data['name'] ?? $destination->name,
            'driver' => $driver,
            'config' => $config,
            'is_active' => $data['is_active'] ?? $destination->is_active,
        ]);

        $this->logger->info('Storage destination updated', [
            'destination_id' => $updated->id,
            'name' => $updated->name,
        ]);

        return $updated;
    }

    public function delete(BackupDestination $destination): void
    {
        $this->repository->delete($destination);

        $this->logger->info('Storage destination deleted', [
            'destination_id' => $destination->id,
            'name' => $destination->name,
        ]);
    }

    public function test(BackupDestination $destination, int $userId): StorageTestResult
    {
        $this->ensureTestRateLimitNotExceeded($userId);

        $result = $this->driverManager
            ->driver($destination->driver)
            ->test($destination->config ?? []);

        $this->repository->update($destination, [
            'last_tested_at' => now(),
            'last_test_status' => $result->success ? 'success' : 'failed',
            'last_test_error' => $result->errorMessage,
            'metadata' => $result->success ? $result->toMetadata() : $destination->metadata,
        ]);

        $this->logTestResult($destination, $result);
        RateLimiter::hit($this->testRateLimitKey($userId), 60);

        return $result;
    }

    public function testConfig(StorageDriver $driver, array $config, int $userId): StorageTestResult
    {
        $this->ensureTestRateLimitNotExceeded($userId);

        $this->driverManager->driver($driver)->validateConfig($config);

        $result = $this->driverManager->driver($driver)->test($config);

        RateLimiter::hit($this->testRateLimitKey($userId), 60);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeConfig(StorageDriver $driver, array $existing, array $incoming): array
    {
        if ($incoming === []) {
            return $existing;
        }

        if ($driver === StorageDriver::Sftp) {
            return app(SftpAuthenticationResolver::class)->mergeConfig($existing, $incoming);
        }

        $secretKeys = match ($driver) {
            StorageDriver::Local => [],
            StorageDriver::S3 => ['secret'],
            default => [],
        };

        foreach ($secretKeys as $key) {
            if (array_key_exists($key, $incoming) && trim((string) $incoming[$key]) === '') {
                $incoming[$key] = $existing[$key] ?? null;
            }
        }

        return array_merge($existing, $incoming);
    }

    private function resolveDriver(mixed $driver): StorageDriver
    {
        if ($driver instanceof StorageDriver) {
            return $driver;
        }

        return StorageDriver::from((string) $driver);
    }

    private function logTestResult(BackupDestination $destination, StorageTestResult $result): void
    {
        if ($result->success) {
            $this->logger->info('Storage destination tested successfully', [
                'destination_id' => $destination->id,
                'name' => $destination->name,
            ]);

            return;
        }

        $this->logger->warning('Storage destination test failed', [
            'destination_id' => $destination->id,
            'name' => $destination->name,
            'error' => $result->errorMessage,
        ]);
    }

    private function ensureTestRateLimitNotExceeded(int $userId): void
    {
        $key = $this->testRateLimitKey($userId);

        if (RateLimiter::tooManyAttempts($key, self::TEST_RATE_LIMIT)) {
            throw StorageDestinationException::testRateLimited(
                RateLimiter::availableIn($key)
            );
        }
    }

    private function testRateLimitKey(int $userId): string
    {
        return "storage-destination-test:{$userId}";
    }
}
