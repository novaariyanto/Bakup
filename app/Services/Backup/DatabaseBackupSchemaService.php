<?php

namespace App\Services\Backup;

use App\Models\DatabaseConnection;
use App\Services\BaseService;
use PDO;
use PDOException;

class DatabaseBackupSchemaService extends BaseService
{
    public function __construct(
        private readonly MySqlDumpConnectionResolver $connectionResolver,
    ) {}

    /**
     * @return list<string>
     */
    public function fetchViewNames(DatabaseConnection $connection): array
    {
        try {
            $pdo = $this->connect($connection);

            $statement = $pdo->query('SHOW FULL TABLES WHERE Table_type = "VIEW"');
            $views = [];

            while ($row = $statement->fetch(PDO::FETCH_NUM)) {
                $views[] = (string) $row[0];
            }

            sort($views);

            return $views;
        } catch (PDOException) {
            return [];
        }
    }

    private function connect(DatabaseConnection $connection): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->connectionResolver->resolveDumpHost($connection->host),
                $connection->port,
                $connection->database_name,
            ),
            $connection->username,
            $connection->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10,
            ],
        );
    }
}
