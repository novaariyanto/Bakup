<?php

namespace App\Exceptions;

class ScheduleException extends BackupManagerException
{
    public static function invalidCron(string $expression): self
    {
        return new self(
            message: "Invalid cron expression: {$expression}",
            userMessage: 'Ekspresi cron tidak valid.',
        );
    }

    public static function missingScheduleConfig(string $field): self
    {
        return new self(
            message: "Missing schedule configuration: {$field}",
            userMessage: 'Konfigurasi jadwal backup belum lengkap.',
        );
    }
}
