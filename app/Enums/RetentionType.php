<?php

namespace App\Enums;

enum RetentionType: string
{
    case KeepLast = 'keep_last';
    case DeleteOlderThanDays = 'delete_older_than_days';

    public function label(): string
    {
        return match ($this) {
            self::KeepLast => 'Simpan X Backup Terakhir',
            self::DeleteOlderThanDays => 'Hapus Lebih Lama dari X Hari',
        };
    }
}
