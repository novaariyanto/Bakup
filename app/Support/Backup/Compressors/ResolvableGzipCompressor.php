<?php

namespace App\Support\Backup\Compressors;

use App\Services\Backup\DatabaseDumpBinaryResolver;
use Spatie\DbDumper\Compressors\Compressor;

class ResolvableGzipCompressor implements Compressor
{
    public function useCommand(): string
    {
        return app(DatabaseDumpBinaryResolver::class)->gzipCommand();
    }

    public function useExtension(): string
    {
        return 'gz';
    }
}
