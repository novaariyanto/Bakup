<?php

namespace App\Services\MyDumper;

use App\Services\BaseService;
use Symfony\Component\Process\Process;

class MyDumperBinaryResolver extends BaseService
{
    public function resolve(): ?string
    {
        $configured = config('mydumper.binary_path');

        if (is_string($configured) && $configured !== '') {
            if ($this->isExecutable($configured)) {
                return $configured;
            }

            $withExtension = $configured.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

            if ($this->isExecutable($withExtension)) {
                return $withExtension;
            }
        }

        return $this->autoDetect();
    }

    public function version(): ?string
    {
        $executable = $this->resolve();

        if ($executable === null) {
            return null;
        }

        $process = new Process([$executable, '--version']);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $output = $process->getOutput().$process->getErrorOutput();

        if (preg_match('/mydumper\s+([\d.]+)/i', $output, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/([\d]+\.[\d]+(?:\.[\d]+)?)/', $output, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public function isCompatible(): bool
    {
        $version = $this->version();

        if ($version === null) {
            return false;
        }

        return version_compare($version, config('mydumper.min_version', '0.10.0'), '>=');
    }

    private function autoDetect(): ?string
    {
        $command = PHP_OS_FAMILY === 'Windows' ? 'where mydumper 2>nul' : 'which mydumper 2>/dev/null';
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        $candidate = trim($output[0]);

        return $this->isExecutable($candidate) ? $candidate : null;
    }

    private function isExecutable(string $path): bool
    {
        return is_file($path) && is_executable($path);
    }
}
