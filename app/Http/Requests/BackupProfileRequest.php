<?php

namespace App\Http\Requests;

use App\Enums\TableDumpMode;
use App\Enums\CompressionType;
use App\Enums\RetentionType;
use App\Enums\ScheduleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BackupProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_folders' => $this->filterEmptyStrings($this->input('include_folders', [])),
            'exclude_folders' => $this->filterEmptyStrings($this->input('exclude_folders', [])),
            'table_dump_modes' => is_array($this->input('table_dump_modes')) ? $this->input('table_dump_modes') : [],
        ]);
    }

    /**
     * @return array<string, list<string|int>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'database_connection_id' => ['required', 'exists:database_connections,id'],
            'backup_database' => ['boolean'],
            'backup_folders' => ['boolean'],
            'include_stored_procedures' => ['boolean'],
            'include_views' => ['boolean'],
            'compression' => ['required', 'in:'.implode(',', array_column(CompressionType::cases(), 'value'))],
            'schedule_type' => ['required', 'in:'.implode(',', array_column(ScheduleType::cases(), 'value'))],
            'retention_type' => ['required', 'in:'.implode(',', array_column(RetentionType::cases(), 'value'))],
            'retention_value' => ['required', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['boolean'],
            'selected_destination_ids' => ['required', 'array', 'min:1'],
            'selected_destination_ids.*' => ['integer', 'exists:backup_destinations,id'],
            'include_folders' => ['array'],
            'include_folders.*' => ['nullable', 'string', 'max:500'],
            'exclude_folders' => ['array'],
            'exclude_folders.*' => ['nullable', 'string', 'max:500'],
            'table_dump_modes' => ['array'],
            'table_dump_modes.*' => ['in:'.implode(',', array_column(TableDumpMode::cases(), 'value'))],
        ];

        $scheduleType = ScheduleType::tryFrom($this->string('schedule_type')->toString()) ?? ScheduleType::Manual;

        if ($scheduleType->requiresCron()) {
            $rules['schedule_cron'] = ['required', 'string', 'max:100'];
        }

        if ($scheduleType->requiresTime()) {
            $rules['schedule_time'] = ['required', 'date_format:H:i'];
        }

        if ($scheduleType->requiresDayOfWeek()) {
            $rules['schedule_day_of_week'] = ['required', 'integer', 'min:0', 'max:6'];
        }

        if ($scheduleType->requiresDayOfMonth()) {
            $rules['schedule_day_of_month'] = ['required', 'integer', 'min:1', 'max:31'];
        }

        if ($this->boolean('backup_folders')) {
            $rules['include_folders'] = ['required', 'array', 'min:1'];
            $rules['include_folders.*'] = ['required', 'string', 'max:500'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('backup_database') && ! $this->boolean('backup_folders')) {
                $validator->errors()->add('backup_database', 'Pilih minimal satu tipe backup (Database atau Folder).');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama backup profile wajib diisi.',
            'database_connection_id.required' => 'Pilih koneksi database.',
            'database_connection_id.exists' => 'Koneksi database tidak valid.',
            'selected_destination_ids.required' => 'Pilih minimal satu storage destination.',
            'selected_destination_ids.min' => 'Pilih minimal satu storage destination.',
            'schedule_time.required' => 'Waktu schedule wajib diisi.',
            'schedule_cron.required' => 'Cron expression wajib diisi.',
            'include_folders.required' => 'Tambahkan minimal satu folder untuk di-backup.',
            'table_dump_modes.*.in' => 'Mode backup tabel tidak valid.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toServicePayload(): array
    {
        $data = $this->validated();
        $scheduleType = $data['schedule_type'];

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'database_connection_id' => $data['database_connection_id'],
            'backup_database' => $data['backup_database'] ?? false,
            'backup_folders' => $data['backup_folders'] ?? false,
            'include_stored_procedures' => $data['include_stored_procedures'] ?? false,
            'include_views' => $data['include_views'] ?? false,
            'compression' => $data['compression'],
            'schedule_type' => $data['schedule_type'],
            'schedule_cron' => $scheduleType === ScheduleType::CustomCron->value ? $this->string('schedule_cron')->toString() : null,
            'schedule_time' => ScheduleType::from($scheduleType)->requiresTime()
                ? $this->string('schedule_time')->toString()
                : null,
            'schedule_day_of_week' => $scheduleType === ScheduleType::Weekly->value
                ? (int) $this->input('schedule_day_of_week')
                : null,
            'schedule_day_of_month' => $scheduleType === ScheduleType::Monthly->value
                ? (int) $this->input('schedule_day_of_month')
                : null,
            'retention_type' => $data['retention_type'],
            'retention_value' => $data['retention_value'],
            'is_active' => $data['is_active'] ?? true,
            'destination_ids' => $this->input('selected_destination_ids', []),
            'include_folders' => $this->input('include_folders', []),
            'exclude_folders' => $this->input('exclude_folders', []),
            'table_dump_modes' => $this->input('table_dump_modes', []),
        ];
    }

    /**
     * @param  array<int, mixed>|null  $values
     * @return list<string>
     */
    private function filterEmptyStrings(?array $values): array
    {
        if ($values === null) {
            return [];
        }

        return array_values(array_filter(
            $values,
            fn ($value) => trim((string) $value) !== '',
        ));
    }
}
