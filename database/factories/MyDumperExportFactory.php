<?php

namespace Database\Factories;

use App\Enums\MyDumper\MyDumperExportStatus;
use App\Enums\MyDumper\MyDumperExportType;
use App\Models\DatabaseConnection;
use App\Models\MyDumperExport;
use App\Models\MyDumperExportProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MyDumperExport>
 */
class MyDumperExportFactory extends Factory
{
    protected $model = MyDumperExport::class;

    public function definition(): array
    {
        return [
            'profile_id' => MyDumperExportProfile::factory(),
            'connection_id' => DatabaseConnection::factory(),
            'storage_destination_id' => fn (array $attributes) => MyDumperExportProfile::find($attributes['profile_id'])?->storage_destination_id,
            'name' => fake()->words(2, true).' Run',
            'database' => 'test_db',
            'type' => MyDumperExportType::Full,
            'status' => MyDumperExportStatus::Waiting,
            'thread' => 4,
            'compression' => false,
            'options_snapshot' => ['build_metadata' => true],
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => MyDumperExportStatus::Running->value,
            'current_stage' => \App\Enums\MyDumper\MyDumperExportStage::Dumping->value,
            'started_at' => now()->subMinutes(5),
        ]);
    }

    public function success(): static
    {
        return $this->state(fn () => [
            'status' => MyDumperExportStatus::Success->value,
            'current_stage' => \App\Enums\MyDumper\MyDumperExportStage::Finished->value,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subMinutes(30),
            'duration' => 1800,
            'total_size' => 1024 * 1024 * 50,
            'file_count' => 10,
            'exit_code' => 0,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => MyDumperExportStatus::Failed->value,
            'current_stage' => \App\Enums\MyDumper\MyDumperExportStage::Finished->value,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subMinutes(50),
            'duration' => 600,
            'exit_code' => 1,
            'message' => 'Export failed',
        ]);
    }
}
