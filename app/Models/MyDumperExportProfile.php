<?php

namespace App\Models;

use App\Enums\MyDumper\MyDumperExportType;
use App\Enums\ScheduleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MyDumperExportProfile extends Model
{
    /** @use HasFactory<\Database\Factories\MyDumperExportProfileFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'mydumper_export_profiles';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'database_connection_id',
        'database',
        'storage_destination_id',
        'export_type',
        'options',
        'selected_tables',
        'exclude_tables',
        'output_folder',
        'threads',
        'compression',
        'schedule_type',
        'schedule_cron',
        'schedule_time',
        'schedule_day_of_week',
        'schedule_day_of_month',
        'next_run_at',
        'last_scheduled_run_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'export_type' => MyDumperExportType::class,
            'options' => 'array',
            'selected_tables' => 'array',
            'exclude_tables' => 'array',
            'threads' => 'integer',
            'compression' => 'boolean',
            'schedule_type' => ScheduleType::class,
            'is_active' => 'boolean',
            'next_run_at' => 'datetime',
            'last_scheduled_run_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MyDumperExportProfile $profile): void {
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

    public function storageDestination(): BelongsTo
    {
        return $this->belongsTo(BackupDestination::class, 'storage_destination_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(MyDumperExport::class, 'profile_id');
    }

    public function resolvedDatabase(): string
    {
        return $this->database ?: $this->databaseConnection?->database_name ?? '';
    }

    public function isScheduled(): bool
    {
        return $this->is_active && $this->schedule_type !== ScheduleType::Manual;
    }
}
