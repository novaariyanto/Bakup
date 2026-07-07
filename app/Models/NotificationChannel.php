<?php

namespace App\Models;

use App\Enums\NotificationDriver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class NotificationChannel extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationChannelFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'driver',
        'config',
        'is_active',
        'notify_on_success',
        'notify_on_failure',
        'notify_on_upload_complete',
        'notify_on_verification_failed',
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
            'driver' => NotificationDriver::class,
            'config' => 'encrypted:array',
            'is_active' => 'boolean',
            'notify_on_success' => 'boolean',
            'notify_on_failure' => 'boolean',
            'notify_on_upload_complete' => 'boolean',
            'notify_on_verification_failed' => 'boolean',
            'metadata' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NotificationChannel $channel): void {
            $channel->uuid ??= (string) Str::uuid();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['config']);
    }

    public function driverLabel(): string
    {
        return $this->driver->label();
    }

    public function summary(): string
    {
        return match ($this->driver) {
            NotificationDriver::Email => $this->config['recipients'] ?? '-',
            NotificationDriver::WhatsApp => $this->config['recipient'] ?? '-',
        };
    }

    public function lastTestSucceeded(): bool
    {
        return $this->last_test_status === 'success';
    }
}
