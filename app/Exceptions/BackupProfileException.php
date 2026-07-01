<?php

namespace App\Exceptions;

class BackupProfileException extends BackupManagerException
{
    public static function connectionUnavailable(): self
    {
        return new self(
            message: 'Database connection is inactive or unavailable.',
            userMessage: 'Koneksi database tidak aktif atau tidak tersedia.',
        );
    }

    public static function tablesFetchFailed(string $reason): self
    {
        return new self(
            message: "Failed to fetch database tables: {$reason}",
            userMessage: "Gagal mengambil daftar tabel: {$reason}",
        );
    }

    public static function noDestinationsSelected(): self
    {
        return new self(
            message: 'At least one storage destination is required.',
            userMessage: 'Pilih minimal satu storage destination.',
        );
    }

    public static function backupTypeRequired(): self
    {
        return new self(
            message: 'At least one backup type must be enabled.',
            userMessage: 'Aktifkan backup database atau folder minimal salah satu.',
        );
    }
}
