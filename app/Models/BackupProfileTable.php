<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupProfileTable extends Model
{
    protected $fillable = [
        'backup_profile_id',
        'table_name',
    ];

    public function backupProfile(): BelongsTo
    {
        return $this->belongsTo(BackupProfile::class);
    }
}
