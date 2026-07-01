<?php

namespace App\Models;

use App\Enums\BackupHistoryStatus;
use App\Enums\CompressionType;
use App\Enums\RetentionType;
use App\Enums\ScheduleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BackupProfile extends Model
{
    /** @use HasFactory<\Database\Factories\BackupProfileFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'database_connection_id',
        'backup_database',
        'backup_folders',
        'include_stored_procedures',
        'include_views',
        'compression',
        'schedule_type',
        'schedule_cron',
        'schedule_time',
        'schedule_day_of_week',
        'schedule_day_of_month',
        'retention_type',
        'retention_value',
        'is_active',
        'next_run_at',
        'last_scheduled_run_at',
    ];

    protected function casts(): array
    {
        return [
            'backup_database' => 'boolean',
            'backup_folders' => 'boolean',
            'include_stored_procedures' => 'boolean',
            'include_views' => 'boolean',
            'compression' => CompressionType::class,
            'schedule_type' => ScheduleType::class,
            'retention_type' => RetentionType::class,
            'is_active' => 'boolean',
            'next_run_at' => 'datetime',
            'last_scheduled_run_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BackupProfile $profile): void {
            $profile->uuid ??= (string) Str::uuid();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    public function databaseConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class);
    }

    public function excludedTables(): HasMany
    {
        return $this->hasMany(BackupProfileTable::class);
    }

    public function includeFolders(): HasMany
    {
        return $this->hasMany(BackupProfileIncludeFolder::class);
    }

    public function excludeFolders(): HasMany
    {
        return $this->hasMany(BackupProfileExcludeFolder::class);
    }

    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(BackupDestination::class, 'backup_profile_destinations')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(BackupHistory::class);
    }

    public function hasRunningBackup(): bool
    {
        return $this->histories()
            ->where('status', BackupHistoryStatus::Running)
            ->exists();
    }

    public function scheduleLabel(): string
    {
        return $this->schedule_type->label();
    }

    public function isScheduled(): bool
    {
        return $this->is_active && $this->schedule_type !== ScheduleType::Manual;
    }

    public function compressionLabel(): string
    {
        return $this->compression->label();
    }

    public function retentionLabel(): string
    {
        return match ($this->retention_type) {
            RetentionType::KeepLast => "Simpan {$this->retention_value} backup terakhir",
            RetentionType::DeleteOlderThanDays => "Hapus > {$this->retention_value} hari",
        };
    }
}
