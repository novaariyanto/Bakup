<?php

namespace App\Exceptions\MyDumper;

use App\Exceptions\BackupManagerException;

class MyDumperException extends BackupManagerException
{
    public static function notInstalled(): self
    {
        return new self(
            message: 'mydumper binary not found.',
            userMessage: 'mydumper tidak ditemukan di PATH. Install mydumper atau set MYDUMPER_BINARY di .env.',
        );
    }

    public static function incompatibleVersion(string $version, string $minVersion): self
    {
        return new self(
            message: "mydumper version {$version} is below minimum {$minVersion}.",
            userMessage: "Versi mydumper ({$version}) tidak kompatibel. Minimum: {$minVersion}.",
        );
    }

    public static function permissionDenied(string $path): self
    {
        return new self(
            message: "Output path not writable: {$path}",
            userMessage: 'Folder output tidak dapat ditulis. Periksa permission disk.',
        );
    }

    public static function connectionFailed(string $reason): self
    {
        return new self(
            message: 'Database connection failed: '.$reason,
            userMessage: 'Gagal terhubung ke database. Periksa kredensial koneksi.',
        );
    }

    public static function diskFull(): self
    {
        return new self(
            message: 'Insufficient disk space for export.',
            userMessage: 'Ruang disk tidak cukup untuk export.',
        );
    }

    public static function alreadyRunning(): self
    {
        return new self(
            message: 'An export is already running for this profile.',
            userMessage: 'Export untuk profile ini masih berjalan.',
        );
    }

    public static function cancelled(): self
    {
        return new self(
            message: 'Export was cancelled by user.',
            userMessage: 'Export dibatalkan oleh pengguna.',
        );
    }

    public static function processFailed(int $exitCode, string $output): self
    {
        return new self(
            message: "mydumper exited with code {$exitCode}: {$output}",
            userMessage: 'Proses mydumper gagal. Periksa log export untuk detail.',
        );
    }

    public static function uploadFailed(string $reason): self
    {
        return new self(
            message: 'Upload failed: '.$reason,
            userMessage: 'Gagal mengunggah hasil export ke storage destination.',
        );
    }

    public static function verificationFailed(string $reason): self
    {
        return new self(
            message: 'Verification failed: '.$reason,
            userMessage: 'Verifikasi export gagal: '.$reason,
        );
    }

    public static function destinationInactive(): self
    {
        return new self(
            message: 'Storage destination is inactive.',
            userMessage: 'Storage destination tidak aktif.',
        );
    }

    public static function profileInactive(): self
    {
        return new self(
            message: 'Export profile is inactive.',
            userMessage: 'Export profile tidak aktif.',
        );
    }
}
