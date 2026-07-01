<?php

namespace App\Contracts\Notification;

use App\DTO\BackupNotificationMessage;
use App\DTO\NotificationTestResult;
use App\Enums\NotificationDriver;

interface NotificationDriverInterface
{
    public function driver(): NotificationDriver;

    /**
     * @param  array<string, mixed>  $config
     */
    public function validateConfig(array $config): void;

    /**
     * @param  array<string, mixed>  $config
     */
    public function test(array $config): NotificationTestResult;

    /**
     * @param  array<string, mixed>  $config
     */
    public function send(array $config, BackupNotificationMessage $message): void;
}
