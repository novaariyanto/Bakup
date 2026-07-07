<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyDumperExportFile extends Model
{
    protected $table = 'mydumper_export_files';

    protected $fillable = [
        'export_id',
        'relative_path',
        'size_bytes',
        'table_name',
        'checksum',
    ];

    public function export(): BelongsTo
    {
        return $this->belongsTo(MyDumperExport::class, 'export_id');
    }

    public function formattedSize(): string
    {
        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }
}
