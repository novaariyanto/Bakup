<?php

namespace App\Exceptions;

use Exception;
use Throwable;

abstract class BackupManagerException extends Exception
{
    public function __construct(
        string $message = '',
        protected string $userMessage = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message ?: $userMessage, $code, $previous);
    }

    public function userMessage(): string
    {
        return $this->userMessage ?: 'Terjadi kesalahan. Silakan coba lagi.';
    }
}
