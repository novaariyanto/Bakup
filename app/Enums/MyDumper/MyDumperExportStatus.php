<?php

namespace App\Enums\MyDumper;

enum MyDumperExportStatus: string
{
    case Waiting = 'waiting';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Running => 'Running',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Waiting => 'badge-zinc',
            self::Running => 'badge-blue',
            self::Success => 'badge-green',
            self::Failed => 'badge-red',
            self::Cancelled => 'badge-amber',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Success, self::Failed, self::Cancelled], true);
    }
}
