<?php

namespace App\Services\Backup;

use App\Services\BaseService;

class DatabaseDumpBinaryResolver extends BaseService
{
    public function resolve(): ?string
    {
        $configured = config('backup.mysql_dump_binary_path');

        if (is_string($configured) && $configured !== '') {
            return $this->normalizeDirectory($configured);
        }

        if (! config('backup.mysql_dump_auto_detect', true)) {
            return null;
        }

        return $this->autoDetect();
    }

    public function mysqldumpExecutable(?string $binaryDirectory = null): ?string
    {
        $directory = $binaryDirectory ?? $this->resolve();

        if ($directory === null) {
            return null;
        }

        $executable = $directory.'mysqldump'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

        return is_file($executable) ? $executable : null;
    }

    public function mysqldumpVersion(): ?string
    {
        $executable = $this->mysqldumpExecutable();

        if ($executable === null) {
            return null;
        }

        $command = escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $executable)).' --version 2>&1';
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        if (preg_match('/Distrib\s+([\d.]+)/i', implode(' ', $output), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    public function shouldDisableColumnStatistics(): bool
    {
        $version = $this->mysqldumpVersion();

        if ($version === null) {
            return false;
        }

        return version_compare($version, '8.0.0', '>=');
    }

    public function gzipExecutable(): ?string
    {
        if (! $this->isGzipEnabled()) {
            return null;
        }

        $configured = config('backup.gzip_binary_path');

        if (is_string($configured) && $configured !== '') {
            return $this->resolveExistingExecutable($configured);
        }

        if (! config('backup.gzip_auto_detect', true)) {
            return null;
        }

        return $this->autoDetectGzip();
    }

    public function isGzipEnabled(): bool
    {
        return (bool) config('backup.gzip_enabled', false);
    }

    public function isGzipAvailable(): bool
    {
        return $this->isGzipEnabled() && $this->gzipExecutable() !== null;
    }

    public function gzipCommand(): string
    {
        if ($this->gzipExecutable() === null) {
            return 'gzip';
        }

        return PHP_OS_FAMILY === 'Windows' ? 'gzip.exe' : 'gzip';
    }

    public function applyProcessPath(): void
    {
        $directories = [];

        if ($mysqldumpDirectory = $this->resolve()) {
            $directories[] = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $mysqldumpDirectory), '\\/');
        }

        if ($gzipExecutable = $this->gzipExecutable()) {
            $directories[] = dirname(str_replace('/', DIRECTORY_SEPARATOR, $gzipExecutable));
        }

        if ($directories === []) {
            return;
        }

        $current = getenv('PATH') ?: '';
        $prefix = implode(PATH_SEPARATOR, array_unique($directories));

        putenv('PATH='.$prefix.($current !== '' ? PATH_SEPARATOR.$current : ''));
    }

    private function autoDetectGzip(): ?string
    {
        $executableName = PHP_OS_FAMILY === 'Windows' ? 'gzip.exe' : 'gzip';

        if (PHP_OS_FAMILY === 'Windows') {
            foreach ($this->windowsGzipCandidatePaths() as $candidate) {
                if (is_file($candidate)) {
                    return str_replace('\\', '/', $candidate);
                }
            }
        }

        return $this->findPreferredExecutableViaSystemPath($executableName);
    }

    /**
     * @return list<string>
     */
    private function windowsGzipCandidatePaths(): array
    {
        $paths = [
            'C:/laragon/bin/git/usr/bin/gzip.exe',
        ];

        foreach (glob('C:/laragon/bin/git/**/usr/bin/gzip.exe') ?: [] as $match) {
            $paths[] = str_replace('\\', '/', $match);
        }

        $paths[] = 'C:/Program Files/Git/usr/bin/gzip.exe';

        return array_values(array_unique($paths));
    }

    private function findPreferredExecutableViaSystemPath(string $executableName): ?string
    {
        $command = PHP_OS_FAMILY === 'Windows'
            ? 'where '.escapeshellarg($executableName)
            : 'command -v '.escapeshellarg($executableName);

        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        $candidates = collect($output)
            ->map(fn (string $path) => trim(str_replace('\\', '/', $path)))
            ->filter(fn (string $path) => $path !== '' && is_file($path))
            ->sortBy(fn (string $path) => str_contains($path, ' ') ? 1 : 0)
            ->values();

        return $candidates->first();
    }

    private function autoDetect(): ?string
    {
        $executableName = PHP_OS_FAMILY === 'Windows' ? 'mysqldump.exe' : 'mysqldump';

        $fromPath = $this->findExecutableDirectoryViaSystemPath($executableName);

        if ($fromPath !== null) {
            return $fromPath;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        foreach ($this->windowsCandidatePatterns($executableName) as $pattern) {
            $matches = glob($pattern);

            if ($matches === false || $matches === []) {
                continue;
            }

            rsort($matches, SORT_NATURAL);

            $match = $matches[0];

            if (is_file($match)) {
                return $this->normalizeDirectory(dirname($match));
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function windowsCandidatePatterns(string $executableName): array
    {
        return [
            'C:/laragon/bin/mysql/mysql-*/bin/'.$executableName,
            'C:/xampp/mysql/bin/'.$executableName,
            'C:/Program Files/MySQL/MySQL Server */bin/'.$executableName,
            'C:/Program Files/MariaDB */bin/'.$executableName,
        ];
    }

    private function findExecutableViaSystemPath(string $executableName): ?string
    {
        return $this->findPreferredExecutableViaSystemPath($executableName);
    }

    private function findExecutableDirectoryViaSystemPath(string $executableName): ?string
    {
        $path = $this->findExecutableViaSystemPath($executableName);

        return $path !== null ? $this->normalizeDirectory(dirname($path)) : null;
    }

    private function normalizeDirectory(string $path): string
    {
        $normalized = str_replace('\\', '/', rtrim(trim($path), '/\\'));

        return $normalized.'/';
    }

    private function resolveExistingExecutable(string $path): ?string
    {
        $candidates = array_unique([
            trim($path),
            str_replace('\\', '/', trim($path)),
            str_replace('/', '\\', trim($path)),
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return str_replace('\\', '/', $candidate);
            }
        }

        return null;
    }
}
