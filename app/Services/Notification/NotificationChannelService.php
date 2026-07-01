<?php

namespace App\Services\Notification;

use App\DTO\BackupNotificationMessage;
use App\DTO\NotificationTestResult;
use App\Enums\NotificationDriver;
use App\Exceptions\NotificationChannelException;
use App\Models\NotificationChannel;
use App\Repositories\NotificationChannelRepository;
use App\Services\BaseService;
use App\Support\BackupLogger;
use Illuminate\Support\Facades\RateLimiter;

class NotificationChannelService extends BaseService
{
    private const TEST_RATE_LIMIT = 10;

    public function __construct(
        private readonly NotificationChannelRepository $repository,
        private readonly NotificationDriverManager $driverManager,
        private readonly BackupLogger $logger,
    ) {}

    public function create(array $data): NotificationChannel
    {
        $driver = $this->resolveDriver($data['driver'] ?? null);
        $config = $data['config'] ?? [];

        $this->driverManager->driver($driver)->validateConfig($config);

        $channel = $this->repository->create([
            'name' => $data['name'],
            'driver' => $driver,
            'config' => $config,
            'is_active' => $data['is_active'] ?? true,
            'notify_on_success' => $data['notify_on_success'] ?? true,
            'notify_on_failure' => $data['notify_on_failure'] ?? true,
        ]);

        $this->logger->info('Notification channel created', [
            'channel_id' => $channel->id,
            'name' => $channel->name,
            'driver' => $driver->value,
        ]);

        return $channel;
    }

    public function update(NotificationChannel $channel, array $data): NotificationChannel
    {
        $driver = isset($data['driver'])
            ? $this->resolveDriver($data['driver'])
            : $channel->driver;

        $config = $this->mergeConfig(
            $driver,
            $channel->config ?? [],
            $data['config'] ?? [],
        );

        $this->driverManager->driver($driver)->validateConfig($config);

        $updated = $this->repository->update($channel, [
            'name' => $data['name'] ?? $channel->name,
            'driver' => $driver,
            'config' => $config,
            'is_active' => $data['is_active'] ?? $channel->is_active,
            'notify_on_success' => $data['notify_on_success'] ?? $channel->notify_on_success,
            'notify_on_failure' => $data['notify_on_failure'] ?? $channel->notify_on_failure,
        ]);

        $this->logger->info('Notification channel updated', [
            'channel_id' => $updated->id,
            'name' => $updated->name,
        ]);

        return $updated;
    }

    public function delete(NotificationChannel $channel): void
    {
        $this->repository->delete($channel);

        $this->logger->info('Notification channel deleted', [
            'channel_id' => $channel->id,
            'name' => $channel->name,
        ]);
    }

    public function test(NotificationChannel $channel, int $userId): NotificationTestResult
    {
        $this->ensureTestRateLimitNotExceeded($userId);

        $result = $this->driverManager
            ->driver($channel->driver)
            ->test($channel->config ?? []);

        $this->repository->update($channel, [
            'last_tested_at' => now(),
            'last_test_status' => $result->success ? 'success' : 'failed',
            'last_test_error' => $result->errorMessage,
            'metadata' => $result->success ? $result->toMetadata() : $channel->metadata,
        ]);

        $this->logTestResult($channel, $result);
        RateLimiter::hit($this->testRateLimitKey($userId), 60);

        return $result;
    }

    public function testConfig(NotificationDriver $driver, array $config, int $userId): NotificationTestResult
    {
        $this->ensureTestRateLimitNotExceeded($userId);

        $this->driverManager->driver($driver)->validateConfig($config);

        $result = $this->driverManager->driver($driver)->test($config);

        RateLimiter::hit($this->testRateLimitKey($userId), 60);

        return $result;
    }

    public function send(NotificationChannel $channel, BackupNotificationMessage $message): void
    {
        $this->driverManager
            ->driver($channel->driver)
            ->send($channel->config ?? [], $message);
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeConfig(NotificationDriver $driver, array $existing, array $incoming): array
    {
        if ($incoming === []) {
            return $existing;
        }

        $secretKeys = match ($driver) {
            NotificationDriver::Email => [],
            NotificationDriver::WhatsApp => ['api_token'],
        };

        foreach ($secretKeys as $key) {
            if (array_key_exists($key, $incoming) && trim((string) $incoming[$key]) === '') {
                $incoming[$key] = $existing[$key] ?? null;
            }
        }

        return array_merge($existing, $incoming);
    }

    private function resolveDriver(mixed $driver): NotificationDriver
    {
        if ($driver instanceof NotificationDriver) {
            return $driver;
        }

        return NotificationDriver::from((string) $driver);
    }

    private function logTestResult(NotificationChannel $channel, NotificationTestResult $result): void
    {
        if ($result->success) {
            $this->logger->info('Notification channel tested successfully', [
                'channel_id' => $channel->id,
                'name' => $channel->name,
            ]);

            return;
        }

        $this->logger->warning('Notification channel test failed', [
            'channel_id' => $channel->id,
            'name' => $channel->name,
            'error' => $result->errorMessage,
        ]);
    }

    private function ensureTestRateLimitNotExceeded(int $userId): void
    {
        $key = $this->testRateLimitKey($userId);

        if (RateLimiter::tooManyAttempts($key, self::TEST_RATE_LIMIT)) {
            throw NotificationChannelException::testRateLimited(
                RateLimiter::availableIn($key)
            );
        }
    }

    private function testRateLimitKey(int $userId): string
    {
        return "notification-channel-test:{$userId}";
    }
}
