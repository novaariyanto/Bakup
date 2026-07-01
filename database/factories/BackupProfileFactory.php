<?php

namespace Database\Factories;

use App\Enums\CompressionType;
use App\Enums\RetentionType;
use App\Enums\ScheduleType;
use App\Models\BackupDestination;
use App\Models\BackupProfile;
use App\Models\DatabaseConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupProfile>
 */
class BackupProfileFactory extends Factory
{
    protected $model = BackupProfile::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Profile',
            'description' => fake()->optional()->sentence(),
            'database_connection_id' => DatabaseConnection::factory(),
            'backup_database' => true,
            'backup_folders' => false,
            'include_stored_procedures' => false,
            'include_views' => false,
            'compression' => CompressionType::Gzip,
            'schedule_type' => ScheduleType::Manual,
            'retention_type' => RetentionType::KeepLast,
            'retention_value' => 7,
            'is_active' => true,
        ];
    }

    public function withFolders(): static
    {
        return $this->state(fn () => [
            'backup_folders' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function daily(string $time = '02:00'): static
    {
        return $this->state(fn () => [
            'schedule_type' => ScheduleType::Daily,
            'schedule_time' => $time,
        ]);
    }

    public function hourly(): static
    {
        return $this->state(fn () => [
            'schedule_type' => ScheduleType::Hourly,
        ]);
    }

    public function weekly(int $dayOfWeek = 1, string $time = '02:00'): static
    {
        return $this->state(fn () => [
            'schedule_type' => ScheduleType::Weekly,
            'schedule_day_of_week' => $dayOfWeek,
            'schedule_time' => $time,
        ]);
    }

    public function cron(string $expression = '0 2 * * *'): static
    {
        return $this->state(fn () => [
            'schedule_type' => ScheduleType::CustomCron,
            'schedule_cron' => $expression,
        ]);
    }

    public function dueNow(): static
    {
        return $this->state(fn () => [
            'next_run_at' => now()->subMinute(),
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (BackupProfile $profile): void {
            if ($profile->destinations()->count() === 0) {
                $destination = BackupDestination::factory()->create();
                $profile->destinations()->attach($destination->id, ['sort_order' => 0]);
            }
        });
    }
}
