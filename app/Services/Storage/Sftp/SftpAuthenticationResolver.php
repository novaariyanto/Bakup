<?php

namespace App\Services\Storage\Sftp;

use App\Enums\SftpAuthenticationMethod;

class SftpAuthenticationResolver
{
    public function resolveMethod(array $config): SftpAuthenticationMethod
    {
        $explicit = $config['auth_method'] ?? null;

        if ($explicit !== null && $explicit !== '') {
            return SftpAuthenticationMethod::from((string) $explicit);
        }

        $privateKey = trim((string) ($config['private_key'] ?? ''));
        $password = trim((string) ($config['password'] ?? ''));

        if ($privateKey !== '' && $password === '') {
            return SftpAuthenticationMethod::PrivateKey;
        }

        return SftpAuthenticationMethod::Password;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function buildFlysystemConfig(array $config): array
    {
        $method = $this->resolveMethod($config);

        $filesystemConfig = [
            'driver' => 'sftp',
            'host' => (string) ($config['host'] ?? ''),
            'port' => (int) ($config['port'] ?? 22),
            'username' => (string) ($config['username'] ?? ''),
            'root' => (string) ($config['root'] ?? '/'),
            'timeout' => 10,
            'throw' => true,
        ];

        if ($method === SftpAuthenticationMethod::Password) {
            $filesystemConfig['password'] = (string) ($config['password'] ?? '');

            return $filesystemConfig;
        }

        $filesystemConfig['privateKey'] = (string) ($config['private_key'] ?? '');

        $passphrase = trim((string) ($config['passphrase'] ?? ''));
        if ($passphrase !== '') {
            $filesystemConfig['passphrase'] = $passphrase;
        }

        return $filesystemConfig;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergeConfig(array $existing, array $incoming): array
    {
        $merged = array_merge($existing, $incoming);
        $method = $this->resolveMethod($merged);

        $merged['auth_method'] = $method->value;

        if ($method === SftpAuthenticationMethod::Password) {
            unset($merged['private_key'], $merged['passphrase']);

            if (array_key_exists('password', $incoming) && trim((string) ($incoming['password'] ?? '')) === '') {
                $merged['password'] = $existing['password'] ?? '';
            }
        } else {
            unset($merged['password']);

            foreach (['private_key', 'passphrase'] as $secretKey) {
                if (array_key_exists($secretKey, $incoming) && trim((string) ($incoming[$secretKey] ?? '')) === '') {
                    $merged[$secretKey] = $existing[$secretKey] ?? '';
                }
            }
        }

        return $this->stripEmptySecrets($merged, $method);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalizeForStorage(array $config): array
    {
        $method = $this->resolveMethod($config);
        $normalized = [
            'host' => (string) ($config['host'] ?? ''),
            'port' => (int) ($config['port'] ?? 22),
            'username' => (string) ($config['username'] ?? ''),
            'auth_method' => $method->value,
            'root' => (string) ($config['root'] ?? '/'),
        ];

        if ($method === SftpAuthenticationMethod::Password) {
            $password = trim((string) ($config['password'] ?? ''));
            if ($password !== '') {
                $normalized['password'] = $password;
            }

            return $normalized;
        }

        $privateKey = trim((string) ($config['private_key'] ?? ''));
        if ($privateKey !== '') {
            $normalized['private_key'] = $privateKey;
        }

        $passphrase = trim((string) ($config['passphrase'] ?? ''));
        if ($passphrase !== '') {
            $normalized['passphrase'] = $passphrase;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function stripEmptySecrets(array $config, SftpAuthenticationMethod $method): array
    {
        if ($method === SftpAuthenticationMethod::Password) {
            unset($config['private_key'], $config['passphrase']);
        } else {
            unset($config['password']);
        }

        foreach (['password', 'private_key', 'passphrase'] as $key) {
            if (array_key_exists($key, $config) && trim((string) $config[$key]) === '') {
                unset($config[$key]);
            }
        }

        return $config;
    }
}
