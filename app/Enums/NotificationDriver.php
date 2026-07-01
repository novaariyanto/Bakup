<?php

namespace App\Enums;

enum NotificationDriver: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Email => 'Kirim notifikasi backup via email SMTP',
            self::WhatsApp => 'Kirim notifikasi backup via API WhatsApp (Fonnte, WATI, dll.)',
        };
    }
}
