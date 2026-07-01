<?php

namespace App\Enums;

enum CompressionType: string
{
    case None = 'none';
    case Gzip = 'gzip';
    case Zip = 'zip';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tanpa Kompresi',
            self::Gzip => 'GZIP Dump DB',
            self::Zip => 'ZIP (Disarankan)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::None => 'File backup tanpa kompresi tambahan.',
            self::Gzip => 'Dump database dikompresi GZIP. Membutuhkan binary gzip di server.',
            self::Zip => 'Database di-dump SQL biasa, lalu seluruh arsip dibungkus ZIP.',
        };
    }

    public function usesGzipDump(): bool
    {
        return $this === self::Gzip;
    }
}
