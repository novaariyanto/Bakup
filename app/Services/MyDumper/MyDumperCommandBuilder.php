<?php

namespace App\Services\MyDumper;

use App\DTO\MyDumper\MyDumperExportOptions;
use App\Enums\MyDumper\MyDumperExportType;
use App\Enums\MyDumper\MyDumperLockMode;
use App\Models\DatabaseConnection;
use App\Models\MyDumperExport;
use App\Models\MyDumperExportProfile;
use App\Services\BaseService;

class MyDumperCommandBuilder extends BaseService
{
    public function __construct(
        private readonly MyDumperBinaryResolver $binaryResolver,
    ) {}
    /**
     * @return array<int, string>
     */
    public function build(
        DatabaseConnection $connection,
        string $database,
        string $outputDirectory,
        MyDumperExportType $exportType,
        int $threads,
        bool $compression,
        MyDumperExportOptions $options,
        ?array $selectedTables = null,
        ?array $excludeTables = null,
        bool $maskPassword = false,
    ): array {
        $password = $connection->password ?? '';

        $command = [
            config('mydumper.binary_path', 'mydumper'),
            '-u', $connection->username,
            '-p', $maskPassword ? '******' : $password,
            '-h', $connection->host,
            '-P', (string) $connection->port,
            '-B', $database,
            '-o', $outputDirectory,
            '-t', (string) max(1, $threads),
        ];

        if ($compression) {
            $command[] = '--compress';
        }

        $this->applyExportType($command, $exportType, $selectedTables, $excludeTables);
        $this->applyOptions($command, $options);

        return $command;
    }

    public function buildFromProfile(MyDumperExportProfile $profile, string $outputDirectory, bool $maskPassword = false): array
    {
        $connection = $profile->databaseConnection;
        $options = MyDumperExportOptions::fromArray($profile->options);

        return $this->build(
            connection: $connection,
            database: $profile->resolvedDatabase(),
            outputDirectory: $outputDirectory,
            exportType: $profile->export_type,
            threads: $profile->threads,
            compression: $profile->compression,
            options: $options,
            selectedTables: $profile->selected_tables,
            excludeTables: $profile->exclude_tables,
            maskPassword: $maskPassword,
        );
    }

    public function buildFromExport(MyDumperExport $export, string $outputDirectory, bool $maskPassword = false): array
    {
        $connection = $export->connection;
        $snapshot = $export->options_snapshot ?? [];
        $options = MyDumperExportOptions::fromArray($snapshot);

        return $this->build(
            connection: $connection,
            database: $export->database,
            outputDirectory: $outputDirectory,
            exportType: $export->type,
            threads: $export->thread,
            compression: $export->compression,
            options: $options,
            selectedTables: $snapshot['selected_tables'] ?? null,
            excludeTables: $snapshot['exclude_tables'] ?? null,
            maskPassword: $maskPassword,
        );
    }

    public function preview(
        DatabaseConnection $connection,
        string $database,
        string $outputDirectory,
        MyDumperExportType $exportType,
        int $threads,
        bool $compression,
        MyDumperExportOptions $options,
        ?array $selectedTables = null,
        ?array $excludeTables = null,
    ): string {
        $command = $this->build(
            connection: $connection,
            database: $database,
            outputDirectory: $outputDirectory,
            exportType: $exportType,
            threads: $threads,
            compression: $compression,
            options: $options,
            selectedTables: $selectedTables,
            excludeTables: $excludeTables,
            maskPassword: true,
        );

        return $this->formatCommand($command);
    }

    /**
     * @param  array<int, string>  $command
     */
    public function formatCommand(array $command): string
    {
        $lines = [];
        $buffer = '';

        foreach ($command as $index => $part) {
            if ($index === 0) {
                $buffer = $part;

                continue;
            }

            $needsQuote = str_contains($part, ' ') || str_contains($part, '\\');

            if (in_array($part, ['-u', '-p', '-h', '-P', '-B', '-o', '-t'], true)) {
                $buffer .= ' '.$part;

                continue;
            }

            if (in_array($buffer, ['mydumper -u', 'mydumper -p', 'mydumper -h', 'mydumper -P', 'mydumper -B', 'mydumper -o', 'mydumper -t'], true)) {
                // fallback for split parts
            }

            $previousFlag = $command[$index - 1] ?? null;

            if (in_array($previousFlag, ['-u', '-p', '-h', '-P', '-B', '-o', '-t'], true)) {
                $buffer .= ' '.($needsQuote ? '"'.$part.'"' : $part);
            } elseif (str_starts_with($part, '--')) {
                if ($buffer !== '') {
                    $lines[] = rtrim($buffer).' \\';
                }
                $buffer = $part;
            } else {
                $buffer .= ' '.$part;
            }
        }

        if ($buffer !== '') {
            $lines[] = rtrim($buffer);
        }

        if ($lines === []) {
            return implode(' ', $command);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $command
     */
    private function applyExportType(
        array &$command,
        MyDumperExportType $exportType,
        ?array $selectedTables,
        ?array $excludeTables,
    ): void {
        match ($exportType) {
            MyDumperExportType::SchemaOnly => $command[] = '--no-data',
            MyDumperExportType::DataOnly => $command[] = '--no-schemas',
            MyDumperExportType::SelectedTables => $this->appendTables($command, $selectedTables ?? []),
            MyDumperExportType::ExcludeTables => $this->appendExcludeTables($command, $excludeTables ?? []),
            MyDumperExportType::Full => null,
        };
    }

    /**
     * @param  array<int, string>  $command
     */
    private function applyOptions(array &$command, MyDumperExportOptions $options): void
    {
        if ($options->buildEmptyFiles) {
            $command[] = '--build-empty-files';
        }

        if ($options->chunkFilesize !== null) {
            $command[] = '--chunk-filesize='.$options->chunkFilesize;
        }

        if ($options->rows !== null) {
            $command[] = '--rows='.$options->rows;
        }

        if ($options->statementSize !== null) {
            $command[] = '--statement-size='.$options->statementSize;
        }

        if ($options->longQueryGuard !== null) {
            $command[] = '--long-query-guard='.$options->longQueryGuard;
        }

        if ($options->killLongQueries) {
            $command[] = '--kill-long-queries';
        }

        $this->applyLockMode($command, $options);

        if ($options->trxConsistencyOnly && $this->supportsTrxConsistencyOnly()) {
            $command[] = '--trx-consistency-only';
        }

        if ($options->skipDefiner) {
            $command[] = '--skip-definer';
        }

        if ($options->skipTriggers) {
            $command[] = '--no-triggers';
        }

        if ($options->skipEvents) {
            $command[] = '--no-events';
        }

        if ($options->skipRoutines) {
            $command[] = '--no-routines';
        }

        if ($options->skipViews) {
            $command[] = '--no-views';
        }

        if ($options->skipConstraints) {
            $command[] = '--skip-constraints';
        }

        if ($options->skipIndexes) {
            $command[] = '--skip-indexes';
        }

        if ($options->skipGeneratedFields) {
            $command[] = '--skip-generated-fields';
        }

        if ($options->regexInclude) {
            $command[] = '--regex='.$options->regexInclude;
        }

        if ($options->regexExclude) {
            $command[] = '--regex='.$options->regexExclude;
        }

        if (! $options->buildMetadata) {
            $command[] = '--no-metadata';
        }

        if ($options->daemonMode) {
            $command[] = '--daemon';
        }
    }

    /**
     * @param  array<int, string>  $command
     */
    private function applyLockMode(array &$command, MyDumperExportOptions $options): void
    {
        if ($options->lockMode === MyDumperLockMode::Auto) {
            return;
        }

        $version = $this->binaryResolver->version();

        if ($version === null || version_compare($version, '0.10.0', '>=')) {
            $command[] = '--sync-thread-lock-mode';
            $command[] = $options->lockMode->cliValue();

            return;
        }

        match ($options->lockMode) {
            MyDumperLockMode::NoLock => $command[] = '--no-locks',
            MyDumperLockMode::LockAll => $command[] = '--lock-all-tables',
            MyDumperLockMode::SafeNoLock => $command[] = '--less-locking',
            MyDumperLockMode::Auto => null,
        };
    }

    private function supportsTrxConsistencyOnly(): bool
    {
        $version = $this->binaryResolver->version();

        return $version === null || version_compare($version, '1.0.1', '<');
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<int, string>  $tables
     */
    private function appendTables(array &$command, array $tables): void
    {
        foreach ($tables as $table) {
            $command[] = '-T';
            $command[] = $table;
        }
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<int, string>  $tables
     */
    private function appendExcludeTables(array &$command, array $tables): void
    {
        foreach ($tables as $table) {
            $command[] = '--ignore-table='.$table;
        }
    }
}
