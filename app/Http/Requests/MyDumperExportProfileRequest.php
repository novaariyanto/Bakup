<?php

namespace App\Http\Requests;

use App\Enums\MyDumper\MyDumperExportType;
use App\Enums\MyDumper\MyDumperLockMode;
use App\Enums\ScheduleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MyDumperExportProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'selected_tables' => array_values(array_filter((array) $this->input('selected_tables', []))),
            'exclude_tables' => array_values(array_filter((array) $this->input('exclude_tables', []))),
            'compression' => $this->boolean('compression'),
            'is_active' => $this->boolean('is_active', true),
            'run_immediately' => $this->boolean('run_immediately', true),
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
            'database' => ['nullable', 'string', 'max:255'],
            'storage_destination_id' => ['required', 'exists:backup_destinations,id'],
            'output_folder' => ['nullable', 'string', 'max:500'],
            'export_type' => ['required', 'in:'.implode(',', array_column(MyDumperExportType::cases(), 'value'))],
            'threads' => ['required', 'integer', 'min:1', 'max:32'],
            'compression' => ['boolean'],
            'schedule_type' => ['required', 'in:'.implode(',', array_column(ScheduleType::cases(), 'value'))],
            'is_active' => ['boolean'],
            'run_immediately' => ['boolean'],
            'selected_tables' => ['array'],
            'selected_tables.*' => ['string', 'max:255', 'regex:/^[A-Za-z0-9_]+$/'],
            'exclude_tables' => ['array'],
            'exclude_tables.*' => ['string', 'max:255', 'regex:/^[A-Za-z0-9_]+$/'],
            'options.build_empty_files' => ['boolean'],
            'options.chunk_filesize' => ['nullable', 'integer', 'min:1'],
            'options.rows' => ['nullable', 'integer', 'min:1'],
            'options.statement_size' => ['nullable', 'integer', 'min:1'],
            'options.long_query_guard' => ['nullable', 'integer', 'min:1'],
            'options.kill_long_queries' => ['boolean'],
            'options.lock_mode' => ['nullable', 'in:'.implode(',', array_column(MyDumperLockMode::cases(), 'value'))],
            'options.trx_consistency_only' => ['boolean'],
            'options.skip_definer' => ['boolean'],
            'options.skip_triggers' => ['boolean'],
            'options.skip_events' => ['boolean'],
            'options.skip_routines' => ['boolean'],
            'options.skip_views' => ['boolean'],
            'options.skip_constraints' => ['boolean'],
            'options.skip_indexes' => ['boolean'],
            'options.skip_generated_fields' => ['boolean'],
            'options.regex_include' => ['nullable', 'string', 'max:500'],
            'options.regex_exclude' => ['nullable', 'string', 'max:500'],
            'options.build_metadata' => ['boolean'],
            'options.daemon_mode' => ['boolean'],
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

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $exportType = MyDumperExportType::tryFrom($this->string('export_type')->toString());

            if ($exportType === MyDumperExportType::SelectedTables && $this->input('selected_tables', []) === []) {
                $validator->errors()->add('selected_tables', 'Pilih minimal satu tabel untuk mode Selected Tables.');
            }

            if ($exportType === MyDumperExportType::ExcludeTables && $this->input('exclude_tables', []) === []) {
                $validator->errors()->add('exclude_tables', 'Pilih minimal satu tabel untuk mode Exclude Tables.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toServicePayload(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'database_connection_id' => $data['database_connection_id'],
            'database' => $data['database'] ?? null,
            'storage_destination_id' => $data['storage_destination_id'],
            'export_type' => $data['export_type'],
            'options' => $data['options'] ?? [],
            'selected_tables' => $data['selected_tables'] ?? null,
            'exclude_tables' => $data['exclude_tables'] ?? null,
            'output_folder' => $data['output_folder'] ?? null,
            'threads' => $data['threads'],
            'compression' => $data['compression'] ?? false,
            'schedule_type' => $data['schedule_type'],
            'schedule_cron' => $data['schedule_cron'] ?? null,
            'schedule_time' => $data['schedule_time'] ?? null,
            'schedule_day_of_week' => $data['schedule_day_of_week'] ?? null,
            'schedule_day_of_month' => $data['schedule_day_of_month'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }
}
