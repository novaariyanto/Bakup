<?php

namespace App\Services\Notification;

use App\Contracts\Notification\NotificationDriverInterface;
use App\Enums\NotificationDriver;
use App\Exceptions\NotificationChannelException;
use App\Services\Notification\Drivers\EmailNotificationDriver;
use App\Services\Notification\Drivers\WhatsAppNotificationDriver;

class NotificationDriverManager
{
    /** @var array<string, NotificationDriverInterface> */
    private array $drivers;

    public function __construct(EmailNotificationDriver $email, WhatsAppNotificationDriver $whatsapp)
    {
        $this->drivers = [
            NotificationDriver::Email->value => $email,
            NotificationDriver::WhatsApp->value => $whatsapp,
        ];
    }

    public function driver(NotificationDriver $driver): NotificationDriverInterface
    {
        if (! isset($this->drivers[$driver->value])) {
            throw NotificationChannelException::unsupportedDriver($driver->value);
        }

        return $this->drivers[$driver->value];
    }
}
