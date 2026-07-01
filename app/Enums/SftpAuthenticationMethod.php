<?php

namespace App\Enums;

enum SftpAuthenticationMethod: string
{
    case Password = 'password';
    case PrivateKey = 'private_key';

    public function label(): string
    {
        return match ($this) {
            self::Password => 'Password',
            self::PrivateKey => 'Private Key',
        };
    }
}
