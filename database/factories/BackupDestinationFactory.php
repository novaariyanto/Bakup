<?php

namespace Database\Factories;

use App\Enums\StorageDriver;
use App\Models\BackupDestination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupDestination>
 */
class BackupDestinationFactory extends Factory
{
    protected $model = BackupDestination::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Storage',
            'driver' => StorageDriver::Local,
            'config' => [
                'path' => 'test/'.fake()->lexify('????'),
            ],
            'is_active' => true,
        ];
    }

    public function local(string $path = 'default'): static
    {
        return $this->state(fn () => [
            'driver' => StorageDriver::Local,
            'config' => ['path' => $path],
        ]);
    }

    public function sftp(): static
    {
        return $this->state(fn () => [
            'driver' => StorageDriver::Sftp,
            'config' => [
                'host' => 'sftp.example.com',
                'port' => 22,
                'username' => 'backup',
                'auth_method' => 'password',
                'password' => 'secret',
                'root' => '/backups',
            ],
        ]);
    }

    public function s3(): static
    {
        return $this->state(fn () => [
            'driver' => StorageDriver::S3,
            'config' => [
                'key' => 'test-key',
                'secret' => 'test-secret',
                'region' => 'us-east-1',
                'bucket' => 'backup-bucket',
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function tested(): static
    {
        return $this->state(fn () => [
            'last_tested_at' => now(),
            'last_test_status' => 'success',
            'metadata' => [
                'status' => 'Writable',
                'resolved_path' => storage_path('app/backups/default'),
            ],
        ]);
    }
}
