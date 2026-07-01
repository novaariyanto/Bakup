<?php

namespace Database\Factories;

use App\Enums\BackupHistoryStatus;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupHistory>
 */
class BackupHistoryFactory extends Factory
{
    protected $model = BackupHistory::class;

    public function definition(): array
    {
        return [
            'backup_profile_id' => BackupProfile::factory(),
            'status' => BackupHistoryStatus::Pending,
            'metadata' => [],
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => BackupHistoryStatus::Running,
            'started_at' => now(),
            'current_stage' => 'preparing',
        ]);
    }

    public function success(): static
    {
        return $this->state(fn () => [
            'status' => BackupHistoryStatus::Success,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
            'duration_seconds' => 300,
            'filename' => 'test-backup.zip',
            'current_stage' => 'finished',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => BackupHistoryStatus::Failed,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
            'message' => 'Backup failed',
        ]);
    }
}
