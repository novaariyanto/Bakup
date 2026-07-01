<?php

namespace App\Exceptions;

class StorageDestinationException extends BackupManagerException
{
    public static function testRateLimited(int $seconds): self
    {
        return new self(
            message: 'Storage destination test rate limit exceeded.',
            userMessage: "Terlalu banyak percobaan test storage. Coba lagi dalam {$seconds} detik.",
        );
    }

    public static function unsupportedDriver(string $driver): self
    {
        return new self(
            message: "Unsupported storage driver: {$driver}",
            userMessage: 'Driver storage belum didukung pada versi ini.',
        );
    }

    public static function invalidConfig(string $message): self
    {
        return new self(
            message: $message,
            userMessage: $message,
        );
    }
}
