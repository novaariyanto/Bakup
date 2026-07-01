<?php

namespace App\Exceptions;

class NotificationChannelException extends BackupManagerException
{
    public static function unsupportedDriver(string $driver): self
    {
        return new self(
            message: "Unsupported notification driver: {$driver}",
            userMessage: 'Driver notifikasi tidak didukung.',
        );
    }

    public static function invalidConfig(string $message): self
    {
        return new self(
            message: $message,
            userMessage: $message,
        );
    }

    public static function testRateLimited(int $seconds): self
    {
        return new self(
            message: 'Notification test rate limit exceeded.',
            userMessage: "Terlalu banyak test notifikasi. Coba lagi dalam {$seconds} detik.",
        );
    }
}
