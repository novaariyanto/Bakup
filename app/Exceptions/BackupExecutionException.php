<?php

namespace App\Exceptions;

class BackupExecutionException extends BackupManagerException
{
    public static function profileInactive(): self
    {
        return new self(
            message: 'Backup profile is inactive.',
            userMessage: 'Backup profile tidak aktif.',
        );
    }

    public static function connectionInactive(): self
    {
        return new self(
            message: 'Database connection is inactive.',
            userMessage: 'Koneksi database tidak aktif.',
        );
    }

    public static function noActiveDestinations(): self
    {
        return new self(
            message: 'No active storage destinations configured.',
            userMessage: 'Tidak ada storage destination aktif.',
        );
    }

    public static function spatieFailed(string $output): self
    {
        return new self(
            message: 'Spatie backup command failed: '.$output,
            userMessage: 'Proses backup gagal. Periksa log untuk detail.',
        );
    }

    public static function fileNotFoundAfterRun(): self
    {
        return new self(
            message: 'Backup completed but file was not found on configured destinations.',
            userMessage: 'Backup selesai tetapi file tidak ditemukan di storage destination.',
        );
    }

    public static function alreadyRunning(): self
    {
        return new self(
            message: 'A backup is already running for this profile.',
            userMessage: 'Backup untuk profile ini masih berjalan.',
        );
    }

    public static function mysqldumpNotFound(): self
    {
        return new self(
            message: 'mysqldump binary not found on the system.',
            userMessage: 'mysqldump tidak ditemukan. Install MySQL/MariaDB client atau set BACKUP_MYSQLDUMP_PATH di .env (contoh Laragon: C:\\laragon\\bin\\mysql\\mysql-5.7.24-winx64\\bin).',
        );
    }

    public static function gzipNotFound(): self
    {
        return new self(
            message: 'gzip binary not found on the system.',
            userMessage: 'gzip tidak ditemukan. Install Git for Windows atau set BACKUP_GZIP_PATH di .env (contoh: C:\\laragon\\bin\\git\\usr\\bin\\gzip.exe).',
        );
    }

    public static function fromDumpFailure(string $technicalMessage): self
    {
        if (str_contains($technicalMessage, 'mysqldump') && str_contains($technicalMessage, 'not recognized')) {
            return self::mysqldumpNotFound();
        }

        if (str_contains($technicalMessage, 'mysqldump') && str_contains($technicalMessage, 'No such file')) {
            return self::mysqldumpNotFound();
        }

        if (str_contains($technicalMessage, 'gzip') && str_contains($technicalMessage, 'not recognized')) {
            return self::gzipNotFound();
        }

        if (str_contains($technicalMessage, 'gzip') && str_contains($technicalMessage, 'No such file')) {
            return self::gzipNotFound();
        }

        if (str_contains($technicalMessage, 'column-statistics') || str_contains($technicalMessage, 'column_statistics')) {
            return new self(
                message: 'Database dump failed: '.$technicalMessage,
                userMessage: 'Versi mysqldump tidak cocok dengan opsi column-statistics. Periksa BACKUP_MYSQLDUMP_PATH atau upgrade mysqldump.',
            );
        }

        if (str_contains($technicalMessage, 'Unknown MySQL server host')) {
            return new self(
                message: 'Database dump failed: '.$technicalMessage,
                userMessage: 'mysqldump gagal terhubung ke MySQL. Pastikan host koneksi database adalah 127.0.0.1 (bukan localhost) dan MySQL Laragon sedang berjalan.',
            );
        }

        if (str_contains($technicalMessage, 'Can\'t create TCP/IP socket') || str_contains($technicalMessage, '10106')) {
            return new self(
                message: 'Database dump failed: '.$technicalMessage,
                userMessage: 'mysqldump gagal membuat koneksi TCP ke MySQL. Pastikan MySQL Laragon aktif dan host database memakai 127.0.0.1.',
            );
        }

        return new self(
            message: 'Database dump failed: '.$technicalMessage,
            userMessage: 'Dump database gagal. Periksa log untuk detail.',
        );
    }
}
