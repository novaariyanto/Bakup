<?php

namespace App\Services\Notification\Drivers;

use App\Contracts\Notification\NotificationDriverInterface;
use App\DTO\BackupNotificationMessage;
use App\DTO\NotificationTestResult;
use App\Enums\NotificationDriver;
use App\Exceptions\NotificationChannelException;

abstract class AbstractNotificationDriver implements NotificationDriverInterface
{
    protected function requireNonEmptyString(array $config, string $key): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw NotificationChannelException::invalidConfig("Field {$key} wajib diisi.");
        }

        return $value;
    }

    protected function testMessage(): BackupNotificationMessage
    {
        return new BackupNotificationMessage(
            subject: 'Backup Manager - Test Notification',
            body: 'Ini adalah pesan test dari Backup Manager. Jika Anda menerima pesan ini, channel notifikasi berhasil dikonfigurasi.',
            event: 'test',
        );
    }
}
