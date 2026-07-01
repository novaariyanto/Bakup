<?php

namespace App\Enums;

enum StorageDriver: string
{
    case Local = 'local';
    case Sftp = 'sftp';
    case S3 = 's3';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Local',
            self::Sftp => 'SFTP',
            self::S3 => 'S3 Compatible',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Local => 'Penyimpanan lokal di server aplikasi',
            self::Sftp => 'Server SFTP remote',
            self::S3 => 'Amazon S3, Cloudflare R2, Wasabi, MinIO, dll.',
        };
    }

    public function isS3Compatible(): bool
    {
        return $this === self::S3;
    }
}
