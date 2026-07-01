<?php

namespace App\Services\Notification\Drivers;

use App\DTO\BackupNotificationMessage;
use App\DTO\NotificationTestResult;
use App\Enums\NotificationDriver;
use Illuminate\Support\Facades\Http;
use Throwable;

class WhatsAppNotificationDriver extends AbstractNotificationDriver
{
    public function driver(): NotificationDriver
    {
        return NotificationDriver::WhatsApp;
    }

    public function validateConfig(array $config): void
    {
        $this->requireNonEmptyString($config, 'api_url');
        $this->requireNonEmptyString($config, 'api_token');
        $this->requireNonEmptyString($config, 'recipient');
    }

    public function test(array $config): NotificationTestResult
    {
        $this->validateConfig($config);

        try {
            $this->send($config, $this->testMessage());

            return new NotificationTestResult(
                success: true,
                status: 'Sent',
                recipient: (string) $config['recipient'],
                endpoint: (string) $config['api_url'],
            );
        } catch (Throwable $exception) {
            return NotificationTestResult::failed($exception->getMessage());
        }
    }

    public function send(array $config, BackupNotificationMessage $message): void
    {
        $this->validateConfig($config);

        $response = Http::withToken((string) $config['api_token'])
            ->acceptJson()
            ->timeout(15)
            ->post((string) $config['api_url'], [
                'target' => (string) $config['recipient'],
                'message' => $this->formatMessage($message),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'WhatsApp API error: '.$response->status().' '.$response->body()
            );
        }
    }

    private function formatMessage(BackupNotificationMessage $message): string
    {
        if ($message->event === 'test') {
            return $message->body;
        }

        return "*{$message->subject}*\n\n{$message->body}";
    }
}
