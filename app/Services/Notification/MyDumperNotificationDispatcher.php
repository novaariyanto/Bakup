<?php

namespace App\Services\Notification;

use App\DTO\BackupNotificationMessage;
use App\Models\MyDumperExport;
use App\Models\NotificationChannel;
use App\Repositories\NotificationChannelRepository;
use App\Support\MyDumperLogger;
use Throwable;

class MyDumperNotificationDispatcher
{
    public function __construct(
        private readonly NotificationChannelRepository $repository,
        private readonly NotificationChannelService $channelService,
        private readonly MyDumperLogger $logger,
    ) {}

    public function dispatchSuccess(MyDumperExport $export): void
    {
        $this->dispatch($export, 'success', $this->buildSuccessMessage($export));
    }

    public function dispatchFailure(MyDumperExport $export, ?string $overrideMessage = null): void
    {
        $this->dispatch($export, 'failure', $this->buildFailureMessage($export, $overrideMessage));
    }

    public function dispatchUploadCompleted(MyDumperExport $export): void
    {
        $channels = NotificationChannel::query()
            ->where('is_active', true)
            ->where('notify_on_upload_complete', true)
            ->get();

        $message = new BackupNotificationMessage(
            subject: 'Upload MyDumper Export Selesai',
            body: "Upload export \"{$export->name}\" selesai.\nDatabase: {$export->database}\nUkuran: ".($export->formattedSize() ?? '-'),
        );

        $this->sendToChannels($channels, $message, $export, 'upload_complete');
    }

    public function dispatchVerificationFailed(MyDumperExport $export, string $reason): void
    {
        $channels = NotificationChannel::query()
            ->where('is_active', true)
            ->where('notify_on_verification_failed', true)
            ->get();

        $message = new BackupNotificationMessage(
            subject: 'Verifikasi MyDumper Export Gagal',
            body: "Verifikasi export \"{$export->name}\" gagal.\nAlasan: {$reason}",
        );

        $this->sendToChannels($channels, $message, $export, 'verification_failed');
    }

    private function dispatch(MyDumperExport $export, string $event, BackupNotificationMessage $message): void
    {
        $channels = $this->repository->activeForEvent($event);
        $this->sendToChannels($channels, $message, $export, $event);
    }

    /**
     * @param  iterable<int, NotificationChannel>  $channels
     */
    private function sendToChannels(iterable $channels, BackupNotificationMessage $message, MyDumperExport $export, string $event): void
    {
        foreach ($channels as $channel) {
            try {
                $this->channelService->send($channel, $message);

                $this->logger->info('MyDumper notification sent', [
                    'channel_id' => $channel->id,
                    'export_id' => $export->id,
                    'event' => $event,
                ]);
            } catch (Throwable $exception) {
                $this->logger->error('MyDumper notification failed', [
                    'channel_id' => $channel->id,
                    'export_id' => $export->id,
                    'event' => $event,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function buildSuccessMessage(MyDumperExport $export): BackupNotificationMessage
    {
        return new BackupNotificationMessage(
            subject: 'MyDumper Export Berhasil',
            body: "Export \"{$export->name}\" berhasil.\nDatabase: {$export->database}\nDurasi: ".($export->formattedDuration() ?? '-')."\nUkuran: ".($export->formattedSize() ?? '-'),
        );
    }

    private function buildFailureMessage(MyDumperExport $export, ?string $overrideMessage): BackupNotificationMessage
    {
        $message = $overrideMessage ?? $export->message ?? 'Export gagal.';

        return new BackupNotificationMessage(
            subject: 'MyDumper Export Gagal',
            body: "Export \"{$export->name}\" gagal.\nDatabase: {$export->database}\nPesan: {$message}",
        );
    }
}
