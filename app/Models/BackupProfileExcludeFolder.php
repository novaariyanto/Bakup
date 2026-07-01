<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupProfileExcludeFolder extends Model
{
    protected $fillable = [
        'backup_profile_id',
        'path',
    ];

    public function backupProfile(): BelongsTo
    {
        return $this->belongsTo(BackupProfile::class);
    }
}
