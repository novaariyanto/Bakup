<?php

namespace App\Support\Backup\Dumpers;

use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Exceptions\DumpFailed;
use Symfony\Component\Process\Process;
use Throwable;

class WindowsCompatibleMySql extends MySql
{
    protected bool $includeViews = false;

    public function setIncludeViews(bool $includeViews = true): self
    {
        $this->includeViews = $includeViews;

        return $this;
    }

    public function dumpToFile(string $dumpFile): void
    {
        if (! $this->isWindows()) {
            parent::dumpToFile($dumpFile);

            return;
        }

        $this->guardAgainstIncompleteCredentials();

        $errors = [];

        foreach ($this->socketCandidates() as $socket) {
            try {
                $this->runWindowsDump($dumpFile, $socket);

                return;
            } catch (DumpFailed $exception) {
                $errors[] = $exception->getMessage();

                if (! $this->isRetryableConnectionError($exception->getMessage())) {
                    throw $exception;
                }
            }
        }

        try {
            $this->runWindowsDump($dumpFile, null);
        } catch (DumpFailed $exception) {
            $errors[] = $exception->getMessage();

            if (! $this->shouldUsePhpFallback($exception->getMessage())) {
                throw $exception;
            }

            try {
                (new PhpMysqlDumper)->dumpToFile(
                    host: $this->host,
                    port: $this->port,
                    database: $this->dbName,
                    username: $this->userName,
                    password: $this->password,
                    dumpFile: $dumpFile,
                    excludeTables: $this->excludeTables,
                    includeViews: $this->includeViews,
                    includeStoredProcedures: in_array('--routines', $this->extraOptions, true),
                );
            } catch (Throwable $fallbackException) {
                throw new DumpFailed(
                    'PHP MySQL dump fallback failed: '.$fallbackException->getMessage().' Previous mysqldump errors: '.implode(' | ', $errors)
                );
            }
        }
    }

    public function getContentsOfCredentialsFile(): string
    {
        if (! $this->isWindows()) {
            return parent::getContentsOfCredentialsFile();
        }

        $contents = [
            '[client]',
            "user = '{$this->userName}'",
            "password = '{$this->password}'",
            "host = '{$this->host}'",
            "port = '{$this->port}'",
        ];

        if ($this->skipSsl) {
            $contents[] = $this->getSSLFlag();
        }

        return implode(PHP_EOL, $contents);
    }

    private function runWindowsDump(string $dumpFile, ?string $socket): void
    {
        $tempFileHandle = tmpfile();
        $this->setTempFileHandle($tempFileHandle);

        fwrite($tempFileHandle, $this->getContentsOfCredentialsFile());
        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];

        $executable = str_replace('/', DIRECTORY_SEPARATOR, $this->dumpBinaryPath.'mysqldump.exe');
        $process = new Process(array_merge(
            [$executable],
            $this->buildWindowsArgumentList($temporaryCredentialsFile, $socket),
        ));
        $process->setTimeout($this->timeout > 0 ? $this->timeout : null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw DumpFailed::processDidNotEndSuccessfully($process);
        }

        file_put_contents($dumpFile, $process->getOutput());

        $this->checkIfDumpWasSuccessFul($process, $dumpFile);
    }

    /**
     * @return list<string>
     */
    private function socketCandidates(): array
    {
        if ($this->socket === '') {
            return [];
        }

        $candidates = [$this->socket];

        if (str_starts_with($this->socket, '/tmp/')) {
            $candidates[] = 'C:/laragon/'.ltrim($this->socket, '/');
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return list<string>
     */
    private function buildWindowsArgumentList(string $credentialsFile, ?string $socket): array
    {
        $arguments = [
            '--defaults-extra-file='.$credentialsFile,
        ];

        if (! $this->createTables) {
            $arguments[] = '--no-create-info';
        }

        if (! $this->includeData) {
            $arguments[] = '--no-data';
        }

        if ($this->skipComments) {
            $arguments[] = '--skip-comments';
        }

        $arguments[] = $this->useExtendedInserts ? '--extended-insert' : '--skip-extended-insert';

        if ($this->useSingleTransaction) {
            $arguments[] = '--single-transaction';
        }

        if ($this->skipLockTables) {
            $arguments[] = '--skip-lock-tables';
        }

        if ($this->doNotUseColumnStatistics) {
            $arguments[] = '--column-statistics=0';
        }

        if ($this->useQuick) {
            $arguments[] = '--quick';
        }

        if ($socket !== null && $socket !== '') {
            $arguments[] = '--socket='.$socket;
        } else {
            $arguments[] = '--protocol=TCP';
        }

        foreach ($this->excludeTables as $tableName) {
            $arguments[] = '--ignore-table='.$this->dbName.'.'.$tableName;
        }

        if ($this->defaultCharacterSet !== '') {
            $arguments[] = '--default-character-set='.$this->defaultCharacterSet;
        }

        foreach ($this->extraOptions as $extraOption) {
            $arguments[] = $extraOption;
        }

        if ($this->setGtidPurged !== 'AUTO') {
            $arguments[] = '--set-gtid-purged='.$this->setGtidPurged;
        }

        if (! $this->dbNameWasSetAsExtraOption) {
            $arguments[] = $this->dbName;
        }

        if ($this->includeTables !== []) {
            $arguments[] = '--tables';
            array_push($arguments, ...$this->includeTables);
        }

        foreach ($this->extraOptionsAfterDbName as $extraOptionAfterDbName) {
            $arguments[] = $extraOptionAfterDbName;
        }

        return $arguments;
    }

    private function isRetryableConnectionError(string $message): bool
    {
        return str_contains($message, 'Unknown MySQL server host')
            || str_contains($message, 'Can\'t create TCP/IP socket')
            || str_contains($message, '10106')
            || str_contains($message, '2002')
            || str_contains($message, '2004');
    }

    private function shouldUsePhpFallback(string $message): bool
    {
        if (! config('backup.mysql_dump_php_fallback', true)) {
            return false;
        }

        return $this->isLocalHost($this->host) && $this->isRetryableConnectionError($message);
    }

    private function isLocalHost(string $host): bool
    {
        $normalized = strtolower(trim($host));

        return in_array($normalized, ['localhost', '127.0.0.1', '::1'], true);
    }
}
