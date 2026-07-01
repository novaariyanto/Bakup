<?php

namespace App\Services\Notification;

use App\DTO\BackupNotificationMessage;
use App\Models\BackupHistory;
use App\Repositories\NotificationChannelRepository;
use App\Support\BackupLogger;
use Throwable;

class BackupNotificationDispatcher
{
    public function __construct(
        private readonly NotificationChannelRepository $repository,
        private readonly NotificationChannelService $channelService,
        private readonly BackupLogger $logger,
    ) {}

    public function dispatchSuccess(BackupHistory $history): void
    {
        $this->dispatch($history, 'success');
    }

    public function dispatchFailure(BackupHistory $history): void
    {
        $this->dispatch($history, 'failure');
    }

    private function dispatch(BackupHistory $history, string $event): void
    {
        $history->loadMissing('backupProfile');

        $message = $this->buildMessage($history, $event);
        $channels = $this->repository->activeForEvent($event);

        foreach ($channels as $channel) {
            try {
                $this->channelService->send($channel, $message);

                $this->logger->info('Backup notification sent', [
                    'channel_id' => $channel->id,
                    'history_id' => $history->id,
                    'event' => $event,
                ]);
            } catch (Throwable $exception) {
                $this->logger->error('Backup notification failed', [
                    'channel_id' => $channel->id,
                    'history_id' => $history->id,
                    'event' => $event,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function buildMessage(BackupHistory $history, string $event): BackupNotificationMessage
    {
        $profileName = $history->backupProfile?->name ?? 'Backup Profile';

        if ($event === 'success') {
            $lines = [
                "Profile: {$profileName}",
                "Status: Berhasil",
                "File: {$history->filename}",
                "Durasi: {$history->duration_seconds} detik",
            ];

            if ($history->formattedSize()) {
                $lines[] = "Ukuran: {$history->formattedSize()}";
            }

            return new BackupNotificationMessage(
                subject: "Backup berhasil: {$profileName}",
                body: implode("\n", array_filter($lines)),
                event: 'backup.success',
            );
        }

        return new BackupNotificationMessage(
            subject: "Backup gagal: {$profileName}",
            body: implode("\n", [
                "Profile: {$profileName}",
                'Status: Gagal',
                "Pesan: {$history->message}",
            ]),
            event: 'backup.failed',
        );
    }
}
