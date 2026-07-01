<?php

use App\DTO\BackupNotificationMessage;
use App\Mail\BackupAlertMail;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Models\NotificationChannel;
use App\Services\Notification\BackupNotificationDispatcher;
use App\Services\Notification\Drivers\EmailNotificationDriver;
use App\Services\Notification\Drivers\WhatsAppNotificationDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('sends email notification to configured recipients', function () {
    Mail::fake();

    $driver = new EmailNotificationDriver;
    $driver->send(
        ['recipients' => 'admin@example.com, ops@example.com'],
        new BackupNotificationMessage('Test Subject', 'Test body', 'test'),
    );

    Mail::assertSent(BackupAlertMail::class);
});

it('rejects invalid email recipients', function () {
    $driver = new EmailNotificationDriver;

    expect(fn () => $driver->validateConfig(['recipients' => 'not-an-email']))
        ->toThrow(App\Exceptions\NotificationChannelException::class);
});

it('sends whatsapp notification via http api', function () {
    Http::fake([
        'https://api.example.com/*' => Http::response(['status' => true], 200),
    ]);

    $driver = new WhatsAppNotificationDriver;
    $driver->send(
        [
            'api_url' => 'https://api.example.com/send',
            'api_token' => 'secret-token',
            'recipient' => '6281234567890',
        ],
        new BackupNotificationMessage('Backup OK', 'Backup selesai', 'backup.success'),
    );

    Http::assertSent(fn ($request) => $request->url() === 'https://api.example.com/send'
        && $request['target'] === '6281234567890');
});

it('dispatches success notification to active success channels', function () {
    Mail::fake();

    $profile = BackupProfile::factory()->create(['name' => 'Prod Backup']);
    NotificationChannel::factory()->email('admin@example.com')->create();
    NotificationChannel::factory()->failureOnly()->create(['name' => 'Failure Only']);

    $history = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'filename' => 'prod.zip',
        'duration_seconds' => 120,
    ]);

    app(BackupNotificationDispatcher::class)->dispatchSuccess($history);

    Mail::assertSent(BackupAlertMail::class);
});

it('dispatches failure notification only to failure channels', function () {
    Mail::fake();

    $profile = BackupProfile::factory()->create();
    NotificationChannel::factory()->email('success-only@example.com')->successOnly()->create();
    NotificationChannel::factory()->email('failure@example.com')->failureOnly()->create();

    $history = BackupHistory::factory()->failed()->create([
        'backup_profile_id' => $profile->id,
        'message' => 'Connection timeout',
    ]);

    app(BackupNotificationDispatcher::class)->dispatchFailure($history);

    Mail::assertSent(BackupAlertMail::class, 1);
});

it('skips inactive notification channels', function () {
    Mail::fake();

    NotificationChannel::factory()->email('inactive@example.com')->inactive()->create();
    $history = BackupHistory::factory()->success()->create();

    app(BackupNotificationDispatcher::class)->dispatchSuccess($history);

    Mail::assertNothingSent();
});
