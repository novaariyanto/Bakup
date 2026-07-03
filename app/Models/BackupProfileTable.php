<?php

namespace App\Models;

use App\Enums\TableDumpMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupProfileTable extends Model
{
    protected $fillable = [
        'backup_profile_id',
        'table_name',
        'dump_mode',
    ];

    protected function casts(): array
    {
        return [
            'dump_mode' => TableDumpMode::class,
        ];
    }

    public function backupProfile(): BelongsTo
    {
        return $this->belongsTo(BackupProfile::class);
    }
}
