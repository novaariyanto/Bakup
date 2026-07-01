<?php

namespace App\Enums;

enum BackupHistoryStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Running => 'Berjalan',
            self::Success => 'Berhasil',
            self::Failed => 'Gagal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Running => 'indigo',
            self::Success => 'emerald',
            self::Failed => 'red',
        };
    }
}
