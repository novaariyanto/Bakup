<?php

namespace Database\Factories;

use App\Enums\MyDumper\MyDumperExportType;
use App\Enums\ScheduleType;
use App\Models\BackupDestination;
use App\Models\DatabaseConnection;
use App\Models\MyDumperExportProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MyDumperExportProfile>
 */
class MyDumperExportProfileFactory extends Factory
{
    protected $model = MyDumperExportProfile::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Export',
            'description' => fake()->optional()->sentence(),
            'database_connection_id' => DatabaseConnection::factory(),
            'database' => null,
            'storage_destination_id' => BackupDestination::factory(),
            'export_type' => MyDumperExportType::Full,
            'options' => ['build_metadata' => true],
            'selected_tables' => null,
            'exclude_tables' => null,
            'output_folder' => 'exports/'.fake()->uuid(),
            'threads' => 4,
            'compression' => false,
            'schedule_type' => ScheduleType::Manual,
            'is_active' => true,
        ];
    }

    public function daily(string $time = '02:00'): static
    {
        return $this->state(fn () => [
            'schedule_type' => ScheduleType::Daily,
            'schedule_time' => $time,
        ]);
    }

    public function dueNow(): static
    {
        return $this->state(fn () => [
            'next_run_at' => now()->subMinute(),
        ]);
    }
}
