<?php

namespace App\Support\Backup\Dumpers;

use PDO;
use RuntimeException;

class PhpMysqlDumper
{
    /**
     * @param  list<string>  $excludeTables
     * @param  list<string>  $structureOnlyTables
     */
    public function dumpToFile(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password,
        string $dumpFile,
        array $excludeTables = [],
        array $structureOnlyTables = [],
        bool $includeViews = false,
        bool $includeStoredProcedures = false,
    ): void {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        );

        $excludeLookup = array_fill_keys($excludeTables, true);
        $structureOnlyLookup = array_fill_keys($structureOnlyTables, true);
        $handle = fopen($dumpFile, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open dump file for writing.');
        }

        try {
            $this->writeLine($handle, '-- Backup Manager PHP MySQL dump');
            $this->writeLine($handle, 'SET NAMES utf8mb4;');
            $this->writeLine($handle, 'SET FOREIGN_KEY_CHECKS=0;');
            $this->writeLine($handle, '');

            $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')
                ->fetchAll(PDO::FETCH_NUM);

            foreach ($tables as [$tableName]) {
                if (isset($excludeLookup[$tableName])) {
                    continue;
                }

                $this->dumpTable($pdo, $handle, $tableName, isset($structureOnlyLookup[$tableName]));
            }

            if ($includeViews) {
                $this->dumpViews($pdo, $handle, $excludeLookup);
            }

            if ($includeStoredProcedures) {
                $this->dumpStoredProcedures($pdo, $handle, $database);
            }

            $this->writeLine($handle, 'SET FOREIGN_KEY_CHECKS=1;');
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, bool>  $excludeLookup
     */
    private function dumpViews(PDO $pdo, mixed $handle, array $excludeLookup): void
    {
        $views = $pdo->query('SHOW FULL TABLES WHERE Table_type = "VIEW"')
            ->fetchAll(PDO::FETCH_NUM);

        foreach ($views as [$viewName]) {
            if (isset($excludeLookup[$viewName])) {
                continue;
            }

            $quotedName = '`'.str_replace('`', '``', $viewName).'`';
            $createStatement = $pdo->query('SHOW CREATE VIEW '.$quotedName)
                ->fetch(PDO::FETCH_ASSOC);

            $this->writeLine($handle, 'DROP VIEW IF EXISTS '.$quotedName.';');
            $this->writeLine($handle, ($createStatement['Create View'] ?? '').';');
            $this->writeLine($handle, '');
        }
    }

    private function dumpStoredProcedures(PDO $pdo, mixed $handle, string $database): void
    {
        $statement = $pdo->prepare(
            'SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ? ORDER BY ROUTINE_TYPE, ROUTINE_NAME'
        );
        $statement->execute([$database]);

        while ($routine = $statement->fetch(PDO::FETCH_ASSOC)) {
            $name = (string) $routine['ROUTINE_NAME'];
            $type = strtoupper((string) $routine['ROUTINE_TYPE']);
            $quotedName = '`'.str_replace('`', '``', $name).'`';
            $command = $type === 'FUNCTION' ? 'SHOW CREATE FUNCTION '.$quotedName : 'SHOW CREATE PROCEDURE '.$quotedName;
            $createStatement = $pdo->query($command)->fetch(PDO::FETCH_ASSOC);
            $column = $type === 'FUNCTION' ? 'Create Function' : 'Create Procedure';

            $this->writeLine($handle, 'DELIMITER ;;');
            $this->writeLine($handle, ($createStatement[$column] ?? '').' ;;');
            $this->writeLine($handle, 'DELIMITER ;');
            $this->writeLine($handle, '');
        }
    }

    private function dumpTable(PDO $pdo, mixed $handle, string $tableName, bool $structureOnly = false): void
    {
        $quotedName = '`'.str_replace('`', '``', $tableName).'`';
        $createStatement = $pdo->query('SHOW CREATE TABLE '.$quotedName)
            ->fetch(PDO::FETCH_ASSOC);

        $this->writeLine($handle, 'DROP TABLE IF EXISTS '.$quotedName.';');
        $this->writeLine($handle, ($createStatement['Create Table'] ?? '').';');
        $this->writeLine($handle, '');

        if ($structureOnly) {
            return;
        }

        $statement = $pdo->query('SELECT * FROM '.$quotedName);

        if ($statement === false) {
            return;
        }

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_keys($row);
            $values = array_map(
                fn (mixed $value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                array_values($row),
            );

            $this->writeLine(
                $handle,
                'INSERT INTO '.$quotedName.' (`'.implode('`, `', $columns).'`) VALUES ('.implode(', ', $values).');',
            );
        }

        $this->writeLine($handle, '');
    }

    private function writeLine(mixed $handle, string $line): void
    {
        fwrite($handle, $line.PHP_EOL);
    }
}
