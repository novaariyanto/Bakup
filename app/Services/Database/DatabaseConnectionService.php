<?php

namespace App\Services\Database;

use App\DTO\ConnectionTestResult;
use App\DTO\DatabaseTableInfo;
use App\Enums\DatabaseDriver;
use App\Exceptions\DatabaseConnectionException;
use App\Models\DatabaseConnection;
use App\Repositories\DatabaseConnectionRepository;
use App\Services\BaseService;
use App\Support\BackupLogger;
use Illuminate\Support\Facades\RateLimiter;
use PDO;
use PDOException;

class DatabaseConnectionService extends BaseService
{
    private const TEST_RATE_LIMIT = 10;

    public function __construct(
        private readonly DatabaseConnectionRepository $repository,
        private readonly BackupLogger $logger,
    ) {}

    public function create(array $data): DatabaseConnection
    {
        $data['driver'] = DatabaseDriver::MySQL;

        $connection = $this->repository->create($data);

        $this->logger->info('Database connection created', [
            'connection_id' => $connection->id,
            'name' => $connection->name,
        ]);

        return $connection;
    }

    public function update(DatabaseConnection $connection, array $data): DatabaseConnection
    {
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $updated = $this->repository->update($connection, $data);

        $this->logger->info('Database connection updated', [
            'connection_id' => $updated->id,
            'name' => $updated->name,
        ]);

        return $updated;
    }

    public function delete(DatabaseConnection $connection): void
    {
        $this->repository->delete($connection);

        $this->logger->info('Database connection deleted', [
            'connection_id' => $connection->id,
            'name' => $connection->name,
        ]);
    }

    public function test(DatabaseConnection $connection, int $userId): ConnectionTestResult
    {
        $this->ensureTestRateLimitNotExceeded($userId);

        if ($connection->driver !== DatabaseDriver::MySQL) {
            throw DatabaseConnectionException::unsupportedDriver($connection->driver->value);
        }

        $result = $this->performMySqlTest($connection);

        $this->repository->update($connection, [
            'last_tested_at' => now(),
            'last_test_status' => $result->success ? 'success' : 'failed',
            'last_test_error' => $result->errorMessage,
            'metadata' => $result->success ? $result->toMetadata() : $connection->metadata,
        ]);

        if ($result->success) {
            $this->logger->info('Connection tested successfully', [
                'connection_id' => $connection->id,
                'name' => $connection->name,
            ]);
        } else {
            $this->logger->warning('Connection test failed', [
                'connection_id' => $connection->id,
                'name' => $connection->name,
                'error' => $result->errorMessage,
            ]);
        }

        RateLimiter::hit($this->testRateLimitKey($userId), 60);

        return $result;
    }

    public function testCredentials(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password,
        int $userId,
    ): ConnectionTestResult {
        $this->ensureTestRateLimitNotExceeded($userId);

        $result = $this->performMySqlTestWithCredentials($host, $port, $database, $username, $password);

        RateLimiter::hit($this->testRateLimitKey($userId), 60);

        return $result;
    }

    private function performMySqlTest(DatabaseConnection $connection): ConnectionTestResult
    {
        return $this->performMySqlTestWithCredentials(
            $connection->host,
            $connection->port,
            $connection->database_name,
            $connection->username,
            $connection->password,
        );
    }

    private function performMySqlTestWithCredentials(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password,
    ): ConnectionTestResult {
        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ],
            );

            $mysqlVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

            $schema = $pdo->query(
                'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
                 FROM information_schema.SCHEMATA
                 WHERE SCHEMA_NAME = DATABASE()'
            )->fetch(PDO::FETCH_ASSOC);

            $sizeBytes = (int) $pdo->query(
                'SELECT COALESCE(SUM(data_length + index_length), 0)
                 FROM information_schema.TABLES
                 WHERE table_schema = DATABASE()'
            )->fetchColumn();

            $tables = $pdo->query('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC);
            $totalTables = count($tables);

            $engineCounts = [];
            foreach ($tables as $table) {
                $engine = $table['Engine'] ?? 'Unknown';
                $engineCounts[$engine] = ($engineCounts[$engine] ?? 0) + 1;
            }

            arsort($engineCounts);
            $primaryEngine = array_key_first($engineCounts) ?: 'Unknown';

            return new ConnectionTestResult(
                success: true,
                mysqlVersion: $mysqlVersion,
                databaseSize: $this->formatBytes($sizeBytes),
                databaseSizeBytes: $sizeBytes,
                totalTables: $totalTables,
                characterSet: $schema['DEFAULT_CHARACTER_SET_NAME'] ?? null,
                collation: $schema['DEFAULT_COLLATION_NAME'] ?? null,
                storageEngine: $primaryEngine,
                status: 'Connected',
            );
        } catch (PDOException $exception) {
            return ConnectionTestResult::failed($exception->getMessage());
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2).' '.$units[$power];
    }

    private function ensureTestRateLimitNotExceeded(int $userId): void
    {
        $key = $this->testRateLimitKey($userId);

        if (RateLimiter::tooManyAttempts($key, self::TEST_RATE_LIMIT)) {
            throw DatabaseConnectionException::testRateLimited(
                RateLimiter::availableIn($key)
            );
        }
    }

    private function testRateLimitKey(int $userId): string
    {
        return "database-connection-test:{$userId}";
    }

    /**
     * @return list<DatabaseTableInfo>
     */
    public function fetchTables(DatabaseConnection $connection): array
    {
        if ($connection->driver !== DatabaseDriver::MySQL) {
            throw DatabaseConnectionException::unsupportedDriver($connection->driver->value);
        }

        try {
            $pdo = new PDO(
                "mysql:host={$connection->host};port={$connection->port};dbname={$connection->database_name};charset=utf8mb4",
                $connection->username,
                $connection->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 10,
                ],
            );

            $tables = $pdo->query('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC);
            $result = [];

            foreach ($tables as $table) {
                $dataLength = (int) ($table['Data_length'] ?? 0);
                $indexLength = (int) ($table['Index_length'] ?? 0);

                $result[] = new DatabaseTableInfo(
                    name: (string) $table['Name'],
                    engine: $table['Engine'] ?? null,
                    rows: isset($table['Rows']) ? (int) $table['Rows'] : null,
                    size: $this->formatBytes($dataLength + $indexLength),
                );
            }

            usort($result, fn (DatabaseTableInfo $a, DatabaseTableInfo $b) => strcmp($a->name, $b->name));

            return $result;
        } catch (PDOException $exception) {
            throw DatabaseConnectionException::fetchFailed($exception->getMessage());
        }
    }
}
