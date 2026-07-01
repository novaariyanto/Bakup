<?php

namespace App\Services\Backup;

use App\Services\BaseService;

class MySqlDumpConnectionResolver extends BaseService
{
    public function __construct(
        private readonly DatabaseDumpBinaryResolver $dumpBinaryResolver,
    ) {}

    public function isLocalHost(string $host): bool
    {
        $normalized = strtolower(trim($host));

        return in_array($normalized, ['localhost', '127.0.0.1', '::1'], true);
    }

    public function resolveDumpHost(string $host): string
    {
        return $this->isLocalHost($host) ? '127.0.0.1' : $host;
    }

    public function resolveSocket(string $host): ?string
    {
        if (! $this->isLocalHost($host)) {
            return null;
        }

        $configured = config('backup.mysql_dump_socket');

        if (is_string($configured) && $configured !== '') {
            return PHP_OS_FAMILY === 'Windows'
                ? $this->normalizeLaragonSocketPath($configured)
                : $this->normalizeSocketPath($configured);
        }

        if (! config('backup.mysql_dump_socket_auto_detect', true)) {
            return null;
        }

        return $this->detectSocketFromMysqldumpConfig();
    }

    /**
     * @return list<string>
     */
    public function resolveWindowsSocketCandidates(string $host): array
    {
        if (! $this->isLocalHost($host) || PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $candidates = [];

        $configured = config('backup.mysql_dump_socket');

        if (is_string($configured) && $configured !== '') {
            $candidates[] = $this->normalizeLaragonSocketPath($configured);
        }

        if (config('backup.mysql_dump_socket_auto_detect', true)) {
            $detected = $this->detectSocketFromMysqldumpConfig();

            if ($detected !== null) {
                $candidates[] = $detected;
                $candidates[] = $this->normalizeLaragonSocketPath($detected);
            }
        }

        $candidates[] = '/tmp/mysql.sock';
        $candidates[] = 'C:/laragon/tmp/mysql.sock';

        return array_values(array_unique(array_filter($candidates)));
    }

    private function normalizeLaragonSocketPath(string $path): string
    {
        $normalized = $this->normalizeSocketPath($path);

        if (! str_starts_with($normalized, '/tmp/')) {
            return $normalized;
        }

        foreach ($this->laragonRootCandidates() as $root) {
            $candidate = $root.'/'.ltrim($normalized, '/');

            if (is_dir(dirname($candidate)) || is_file($candidate)) {
                return $candidate;
            }
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function laragonRootCandidates(): array
    {
        $candidates = ['C:/laragon'];

        $mysqldumpDirectory = $this->dumpBinaryResolver->resolve();

        if ($mysqldumpDirectory !== null) {
            $normalized = str_replace('\\', '/', rtrim($mysqldumpDirectory, '/\\'));

            if (preg_match('#^(.*?/laragon)/#i', $normalized, $matches) === 1) {
                $candidates[] = $matches[1];
            }
        }

        return array_values(array_unique($candidates));
    }

    private function detectSocketFromMysqldumpConfig(): ?string
    {
        $mysqldumpDirectory = $this->dumpBinaryResolver->resolve();

        if ($mysqldumpDirectory === null) {
            return null;
        }

        $binaryRoot = rtrim(str_replace('\\', '/', dirname(rtrim($mysqldumpDirectory, '/\\'))), '/');
        $configCandidates = [
            $binaryRoot.'/my.ini',
            $binaryRoot.'/my.cnf',
        ];

        foreach ($configCandidates as $configPath) {
            $socket = $this->parseSocketFromConfigFile($configPath);

            if ($socket !== null) {
                return $socket;
            }
        }

        return null;
    }

    private function parseSocketFromConfigFile(string $configPath): ?string
    {
        if (! is_file($configPath)) {
            return null;
        }

        $contents = @file_get_contents($configPath);

        if ($contents === false) {
            return null;
        }

        if (preg_match('/^\s*socket\s*=\s*(.+)\s*$/mi', $contents, $matches) !== 1) {
            return null;
        }

        $socket = trim($matches[1], " \t\"'");

        return $socket !== '' ? $this->normalizeSocketPath($socket) : null;
    }

    private function normalizeSocketPath(string $path): string
    {
        return str_replace('\\', '/', trim($path));
    }
}
