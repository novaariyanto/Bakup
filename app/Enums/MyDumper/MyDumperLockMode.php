<?php

namespace App\Enums\MyDumper;

enum MyDumperLockMode: string
{
    case Auto = 'auto';
    case SafeNoLock = 'safe_no_lock';
    case LockAll = 'lock_all';
    case NoLock = 'no_lock';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'AUTO',
            self::SafeNoLock => 'SAFE_NO_LOCK',
            self::LockAll => 'LOCK_ALL',
            self::NoLock => 'NO_LOCK',
        };
    }

    public function cliValue(): string
    {
        return match ($this) {
            self::Auto => 'AUTO',
            self::SafeNoLock => 'SAFE_NO_LOCK',
            self::LockAll => 'LOCK_ALL',
            self::NoLock => 'NO_LOCK',
        };
    }
}
