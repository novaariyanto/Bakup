<?php

namespace App\Services\Notification\Drivers;

use App\DTO\BackupNotificationMessage;
use App\DTO\NotificationTestResult;
use App\Enums\NotificationDriver;
use App\Exceptions\NotificationChannelException;
use App\Mail\BackupAlertMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailNotificationDriver extends AbstractNotificationDriver
{
    public function driver(): NotificationDriver
    {
        return NotificationDriver::Email;
    }

    public function validateConfig(array $config): void
    {
        $recipients = $this->parseRecipients($config);

        if ($recipients === []) {
            throw NotificationChannelException::invalidConfig('Minimal satu alamat email penerima wajib diisi.');
        }

        foreach ($recipients as $recipient) {
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw NotificationChannelException::invalidConfig("Email tidak valid: {$recipient}");
            }
        }
    }

    public function test(array $config): NotificationTestResult
    {
        $this->validateConfig($config);

        try {
            $this->send($config, $this->testMessage());

            return new NotificationTestResult(
                success: true,
                status: 'Sent',
                recipient: implode(', ', $this->parseRecipients($config)),
            );
        } catch (Throwable $exception) {
            return NotificationTestResult::failed($exception->getMessage());
        }
    }

    public function send(array $config, BackupNotificationMessage $message): void
    {
        $this->validateConfig($config);

        $recipients = $this->parseRecipients($config);
        $prefix = trim((string) ($config['subject_prefix'] ?? ''));

        $subject = $prefix !== ''
            ? "{$prefix} {$message->subject}"
            : $message->subject;

        Mail::to($recipients)->send(new BackupAlertMail($subject, $message->body));
    }

    /**
     * @return list<string>
     */
    private function parseRecipients(array $config): array
    {
        $raw = $config['recipients'] ?? '';

        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = preg_split('/[\s,;]+/', (string) $raw) ?: [];
        }

        return array_values(array_filter(array_map(
            fn (string $email) => trim($email),
            $items,
        )));
    }
}
