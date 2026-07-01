<?php

namespace App\Services\Storage;

use App\Contracts\Storage\StorageDriverInterface;
use App\Enums\StorageDriver;
use App\Exceptions\StorageDestinationException;
use App\Services\Storage\Drivers\LocalStorageDriver;
use App\Services\Storage\Drivers\S3StorageDriver;
use App\Services\Storage\Drivers\SftpStorageDriver;

class StorageDriverManager
{
    /** @var array<string, StorageDriverInterface> */
    private array $drivers;

    public function __construct(LocalStorageDriver $local, SftpStorageDriver $sftp, S3StorageDriver $s3)
    {
        $this->drivers = [
            StorageDriver::Local->value => $local,
            StorageDriver::Sftp->value => $sftp,
            StorageDriver::S3->value => $s3,
        ];
    }

    public function driver(StorageDriver $driver): StorageDriverInterface
    {
        if (! isset($this->drivers[$driver->value])) {
            throw StorageDestinationException::unsupportedDriver($driver->value);
        }

        return $this->drivers[$driver->value];
    }
}
