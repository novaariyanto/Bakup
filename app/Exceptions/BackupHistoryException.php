<?php

namespace App\Exceptions;

class BackupHistoryException extends BackupManagerException
{
    public static function cannotDeleteRunning(): self
    {
        return new self(
            message: 'Cannot delete a running backup history.',
            userMessage: 'Backup yang masih berjalan tidak dapat dihapus.',
        );
    }

    public static function cannotRetry(): self
    {
        return new self(
            message: 'Only failed backups can be retried.',
            userMessage: 'Hanya backup yang gagal dapat di-retry.',
        );
    }

    public static function fileNotFound(): self
    {
        return new self(
            message: 'Backup file not found on storage.',
            userMessage: 'File backup tidak ditemukan di storage.',
        );
    }
}
