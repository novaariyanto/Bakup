<?php

namespace App\Models;

use App\Enums\BackupHistoryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BackupHistory extends Model
{
    /** @use HasFactory<\Database\Factories\BackupHistoryFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'backup_profile_id',
        'triggered_by',
        'status',
        'filename',
        'current_stage',
        'original_size_bytes',
        'compressed_size_bytes',
        'duration_seconds',
        'message',
        'metadata',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BackupHistoryStatus::class,
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BackupHistory $history): void {
            $history->uuid ??= (string) Str::uuid();
        });
    }

    public function backupProfile(): BelongsTo
    {
        return $this->belongsTo(BackupProfile::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BackupLog::class)->orderBy('created_at');
    }

    public function isRunning(): bool
    {
        return $this->status === BackupHistoryStatus::Running;
    }

    public function hasSucceeded(): bool
    {
        return $this->status === BackupHistoryStatus::Success;
    }

    public function formattedSize(): ?string
    {
        $bytes = $this->compressed_size_bytes ?? $this->original_size_bytes;

        if ($bytes === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }
}
