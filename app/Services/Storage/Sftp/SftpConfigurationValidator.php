<?php

namespace App\Services\Storage\Sftp;

use App\Enums\SftpAuthenticationMethod;
use App\Exceptions\StorageDestinationException;

class SftpConfigurationValidator
{
    public function __construct(
        private readonly SftpAuthenticationResolver $authenticationResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function validate(array $config, bool $requireSecrets = true): void
    {
        $this->requireNonEmpty($config, 'host', 'Host SFTP wajib diisi.');
        $this->requireNonEmpty($config, 'username', 'Username SFTP wajib diisi.');

        $port = (int) ($config['port'] ?? 22);
        if ($port < 1 || $port > 65535) {
            throw StorageDestinationException::invalidConfig('Port SFTP tidak valid.');
        }

        $method = $this->authenticationResolver->resolveMethod($config);

        if ($method === SftpAuthenticationMethod::Password) {
            $this->validatePasswordAuth($config, $requireSecrets);

            return;
        }

        $this->validatePrivateKeyAuth($config, $requireSecrets);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function validatePasswordAuth(array $config, bool $requireSecrets): void
    {
        $password = trim((string) ($config['password'] ?? ''));
        $privateKey = trim((string) ($config['private_key'] ?? ''));

        if ($privateKey !== '') {
            throw StorageDestinationException::invalidConfig(
                'Metode autentikasi Password tidak boleh disertai private key.'
            );
        }

        if ($requireSecrets && $password === '') {
            throw StorageDestinationException::invalidConfig('Password SFTP wajib diisi untuk metode Password.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function validatePrivateKeyAuth(array $config, bool $requireSecrets): void
    {
        $privateKey = trim((string) ($config['private_key'] ?? ''));

        if ($requireSecrets && $privateKey === '') {
            throw StorageDestinationException::invalidConfig('Private key wajib diisi untuk metode Private Key.');
        }

        if ($privateKey !== '') {
            $this->validatePrivateKeyFormat($privateKey);
        }
    }

    public function validatePrivateKeyFormat(string $privateKey): void
    {
        $key = trim($privateKey);

        if ($key === '') {
            throw StorageDestinationException::invalidConfig('Private key tidak boleh kosong.');
        }

        $supportedHeaders = [
            '-----BEGIN RSA PRIVATE KEY-----',
            '-----BEGIN OPENSSH PRIVATE KEY-----',
            '-----BEGIN EC PRIVATE KEY-----',
            '-----BEGIN DSA PRIVATE KEY-----',
            '-----BEGIN PRIVATE KEY-----',
        ];

        $hasValidHeader = false;
        foreach ($supportedHeaders as $header) {
            if (str_contains($key, $header)) {
                $hasValidHeader = true;
                break;
            }
        }

        if (! $hasValidHeader) {
            throw StorageDestinationException::invalidConfig(
                'Format private key tidak valid. Gunakan PEM key (RSA atau OpenSSH).'
            );
        }

        if (! preg_match('/-----END [A-Z ]+PRIVATE KEY-----/', $key)) {
            throw StorageDestinationException::invalidConfig(
                'Format private key tidak valid. Footer PEM key tidak ditemukan.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function requireNonEmpty(array $config, string $key, string $message): void
    {
        if (trim((string) ($config[$key] ?? '')) === '') {
            throw StorageDestinationException::invalidConfig($message);
        }
    }
}
