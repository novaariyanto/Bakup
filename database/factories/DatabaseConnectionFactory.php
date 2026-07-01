<?php

namespace Database\Factories;

use App\Enums\DatabaseDriver;
use App\Models\DatabaseConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatabaseConnection>
 */
class DatabaseConnectionFactory extends Factory
{
    protected $model = DatabaseConnection::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' DB',
            'driver' => DatabaseDriver::MySQL,
            'host' => '127.0.0.1',
            'port' => 3306,
            'database_name' => fake()->lexify('db_????'),
            'username' => 'root',
            'password' => 'secret',
            'is_active' => true,
        ];
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
                'mysql_version' => '8.0.36',
                'database_size' => '12.5 MB',
                'database_size_bytes' => 13107200,
                'total_tables' => 24,
                'character_set' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'storage_engine' => 'InnoDB',
                'status' => 'Connected',
            ],
        ]);
    }
}
