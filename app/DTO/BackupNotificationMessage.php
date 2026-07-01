<?php

namespace App\DTO;

readonly class BackupNotificationMessage
{
    public function __construct(
        public string $subject,
        public string $body,
        public string $event,
    ) {}
}
