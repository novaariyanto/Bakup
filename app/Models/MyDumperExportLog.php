<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyDumperExportLog extends Model
{
    public $timestamps = false;

    protected $table = 'mydumper_export_logs';

    protected $fillable = [
        'export_id',
        'level',
        'stream',
        'message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function export(): BelongsTo
    {
        return $this->belongsTo(MyDumperExport::class, 'export_id');
    }
}
