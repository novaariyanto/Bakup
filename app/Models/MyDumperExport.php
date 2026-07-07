<?php

namespace App\Models;

use App\Enums\MyDumper\MyDumperExportStage;
use App\Enums\MyDumper\MyDumperExportStatus;
use App\Enums\MyDumper\MyDumperExportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MyDumperExport extends Model
{
    /** @use HasFactory<\Database\Factories\MyDumperExportFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'mydumper_exports';

    protected $fillable = [
        'uuid',
        'profile_id',
        'connection_id',
        'storage_destination_id',
        'name',
        'database',
        'type',
        'status',
        'current_stage',
        'thread',
        'compression',
        'output_path',
        'command',
        'log_path',
        'metadata_path',
        'total_size',
        'file_count',
        'duration',
        'exit_code',
        'progress_percent',
        'current_table',
        'current_file',
        'rows_exported',
        'tables_total',
        'tables_completed',
        'eta_seconds',
        'process_pid',
        'checksum',
        'verification_status',
        'verification_message',
        'options_snapshot',
        'metadata',
        'message',
        'started_at',
        'finished_at',
        'cancelled_at',
        'cancelled_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MyDumperExportType::class,
            'status' => MyDumperExportStatus::class,
            'current_stage' => MyDumperExportStage::class,
            'compression' => 'boolean',
            'options_snapshot' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MyDumperExport $export): void {
            $export->uuid ??= (string) Str::uuid();
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(MyDumperExportProfile::class, 'profile_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class, 'connection_id');
    }

    public function storageDestination(): BelongsTo
    {
        return $this->belongsTo(BackupDestination::class, 'storage_destination_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MyDumperExportLog::class, 'export_id')->orderBy('created_at');
    }

    public function files(): HasMany
    {
        return $this->hasMany(MyDumperExportFile::class, 'export_id')->orderBy('relative_path');
    }

    public function isRunning(): bool
    {
        return $this->status === MyDumperExportStatus::Running;
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function formattedSize(): ?string
    {
        if ($this->total_size === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $this->total_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }

    public function formattedDuration(): ?string
    {
        if ($this->duration === null) {
            return null;
        }

        $hours = intdiv($this->duration, 3600);
        $minutes = intdiv($this->duration % 3600, 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        }

        if ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        }

        return sprintf('%ds', $seconds);
    }
}
