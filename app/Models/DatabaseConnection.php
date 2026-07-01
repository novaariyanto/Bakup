<?php

namespace App\Models;

use App\Enums\DatabaseDriver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DatabaseConnection extends Model
{
    /** @use HasFactory<\Database\Factories\DatabaseConnectionFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'driver',
        'host',
        'port',
        'database_name',
        'username',
        'password',
        'is_active',
        'metadata',
        'last_tested_at',
        'last_test_status',
        'last_test_error',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'driver' => DatabaseDriver::class,
            'password' => 'encrypted',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DatabaseConnection $connection): void {
            $connection->uuid ??= (string) Str::uuid();
            $connection->driver ??= DatabaseDriver::MySQL;
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['password']);
    }

    public function lastTestSucceeded(): bool
    {
        return $this->last_test_status === 'success';
    }

    public function formattedDatabaseSize(): ?string
    {
        return $this->metadata['database_size'] ?? null;
    }

    public function backupProfiles(): HasMany
    {
        return $this->hasMany(BackupProfile::class);
    }
}
