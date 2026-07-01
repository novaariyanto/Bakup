<?php

namespace App\Models;

use App\Enums\StorageDriver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BackupDestination extends Model
{
    /** @use HasFactory<\Database\Factories\BackupDestinationFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'driver',
        'config',
        'is_active',
        'metadata',
        'last_tested_at',
        'last_test_status',
        'last_test_error',
    ];

    protected $hidden = [
        'config',
    ];

    protected function casts(): array
    {
        return [
            'driver' => StorageDriver::class,
            'config' => 'encrypted:array',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BackupDestination $destination): void {
            $destination->uuid ??= (string) Str::uuid();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['config']);
    }

    public function lastTestSucceeded(): bool
    {
        return $this->last_test_status === 'success';
    }

    public function driverLabel(): string
    {
        return $this->driver->label();
    }

    public function summary(): string
    {
        return match ($this->driver) {
            StorageDriver::Local => $this->config['path'] ?? '-',
            StorageDriver::Sftp => ($this->config['host'] ?? '-').':'.($this->config['port'] ?? 22),
            StorageDriver::S3 => $this->config['bucket'] ?? '-',
        };
    }

    public function backupProfiles(): BelongsToMany
    {
        return $this->belongsToMany(BackupProfile::class, 'backup_profile_destinations')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
