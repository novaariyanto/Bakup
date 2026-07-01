<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
        };
    }
}
