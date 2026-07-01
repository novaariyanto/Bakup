<?php

namespace App\Enums;

enum DatabaseDriver: string
{
    case MySQL = 'mysql';
    case PostgreSQL = 'pgsql';
    case SQLite = 'sqlite';

    public function label(): string
    {
        return match ($this) {
            self::MySQL => 'MySQL',
            self::PostgreSQL => 'PostgreSQL',
            self::SQLite => 'SQLite',
        };
    }

    public function defaultPort(): int
    {
        return match ($this) {
            self::MySQL => 3306,
            self::PostgreSQL => 5432,
            self::SQLite => 0,
        };
    }
}
