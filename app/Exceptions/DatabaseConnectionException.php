<?php

namespace App\Exceptions;

class DatabaseConnectionException extends BackupManagerException
{
    public static function testRateLimited(int $seconds): self
    {
        return new self(
            message: 'Database connection test rate limit exceeded.',
            userMessage: "Terlalu banyak percobaan test koneksi. Coba lagi dalam {$seconds} detik.",
        );
    }

    public static function unsupportedDriver(string $driver): self
    {
        return new self(
            message: "Unsupported database driver: {$driver}",
            userMessage: 'Driver database belum didukung pada versi ini.',
        );
    }

    public static function fetchFailed(string $reason): self
    {
        return new self(
            message: "Failed to fetch database tables: {$reason}",
            userMessage: "Gagal mengambil daftar tabel: {$reason}",
        );
    }
}
