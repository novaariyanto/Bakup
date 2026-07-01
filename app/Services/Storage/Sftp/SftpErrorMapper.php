<?php

namespace App\Services\Storage\Sftp;

use App\Exceptions\StorageDestinationException;
use League\Flysystem\PhpseclibV3\UnableToAuthenticate;
use League\Flysystem\PhpseclibV3\UnableToConnectToSftpHost;
use League\Flysystem\PhpseclibV3\UnableToLoadPrivateKey;
use League\Flysystem\UnableToWriteFile;
use Throwable;

class SftpErrorMapper
{
    public function map(Throwable $exception): string
    {
        if ($exception instanceof StorageDestinationException) {
            return $exception->userMessage();
        }

        if ($exception instanceof UnableToLoadPrivateKey) {
            return 'Invalid private key format: private key tidak dapat dimuat atau format tidak dikenali.';
        }

        if ($exception instanceof UnableToAuthenticate) {
            return $this->mapAuthenticationFailure($exception);
        }

        if ($exception instanceof UnableToConnectToSftpHost) {
            return $this->mapConnectionFailure($exception);
        }

        if ($exception instanceof UnableToWriteFile) {
            return 'Cannot write to remote directory: tidak dapat menulis file uji ke direktori SFTP.';
        }

        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'permission denied')) {
            return 'Permission denied: akun SFTP tidak memiliki izin yang cukup.';
        }

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return 'Connection timeout: server SFTP tidak merespons dalam batas waktu.';
        }

        if (str_contains($message, 'unable to write file') || str_contains($message, 'cannot write')) {
            return 'Cannot write to remote directory: tidak dapat menulis file uji ke direktori SFTP.';
        }

        if (str_contains($message, 'authentication failed') || str_contains($message, 'auth fail')) {
            return 'Authentication failed: username atau kredensial ditolak server.';
        }

        if (str_contains($message, 'could not connect') || str_contains($message, 'connection refused')) {
            return 'Host unreachable: tidak dapat terhubung ke server SFTP.';
        }

        return 'Koneksi SFTP gagal. Periksa host, port, username, dan metode autentikasi.';
    }

    private function mapAuthenticationFailure(UnableToAuthenticate $exception): string
    {
        $message = strtolower($exception->getMessage());
        $connectionError = strtolower((string) $exception->connectionError());

        if (str_contains($message, 'password') || str_contains($connectionError, 'password')) {
            return 'Invalid username/password: autentikasi password ditolak server.';
        }

        if (str_contains($message, 'private key') || str_contains($connectionError, 'publickey')) {
            return 'Authentication failed: private key ditolak server atau tidak cocok dengan username.';
        }

        return 'Authentication failed: kredensial SFTP ditolak server.';
    }

    private function mapConnectionFailure(UnableToConnectToSftpHost $exception): string
    {
        $previous = $exception->getPrevious();
        $details = strtolower($previous?->getMessage() ?? $exception->getMessage());

        if (str_contains($details, 'timed out') || str_contains($details, 'timeout')) {
            return 'Connection timeout: server SFTP tidak merespons.';
        }

        if (str_contains($details, 'could not resolve') || str_contains($details, 'name or service not known')) {
            return 'Host unreachable: hostname SFTP tidak dapat ditemukan.';
        }

        return 'Host unreachable: tidak dapat terhubung ke server SFTP.';
    }
}
