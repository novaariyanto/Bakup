<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    protected $fillable = [
        'backup_history_id',
        'stage',
        'level',
        'message',
    ];

    public function backupHistory(): BelongsTo
    {
        return $this->belongsTo(BackupHistory::class);
    }
}
