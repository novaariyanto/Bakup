<?php

use App\Mail\BackupAlertMail;
use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('requires authentication to access notifications', function () {
    $this->get(route('notifications.index'))
        ->assertRedirect(route('login'));
});

it('renders notifications page for administrator', function () {
    actingAsAdmin();

    $this->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Notifications');
});

it('creates an email notification channel', function () {
    actingAsAdmin();

    $this->post(route('notifications.store'), [
        'name' => 'Ops Email',
        'driver' => 'email',
        'email_recipients' => 'ops@example.com',
        'notify_on_success' => '1',
        'notify_on_failure' => '1',
        'is_active' => '1',
    ])->assertRedirect(route('notifications.index'));

    $this->assertDatabaseHas('notification_channels', [
        'name' => 'Ops Email',
        'driver' => 'email',
        'is_active' => true,
    ]);
});

it('creates a whatsapp notification channel', function () {
    actingAsAdmin();

    $this->post(route('notifications.store'), [
        'name' => 'Ops WhatsApp',
        'driver' => 'whatsapp',
        'whatsapp_api_url' => 'https://api.example.com/send',
        'whatsapp_api_token' => 'test-token',
        'whatsapp_recipient' => '6281234567890',
        'notify_on_success' => '1',
        'notify_on_failure' => '1',
        'is_active' => '1',
    ])->assertRedirect(route('notifications.index'));

    $this->assertDatabaseHas('notification_channels', [
        'name' => 'Ops WhatsApp',
        'driver' => 'whatsapp',
    ]);
});

it('updates a notification channel without changing token', function () {
    actingAsAdmin();

    $channel = NotificationChannel::factory()->whatsapp()->create(['name' => 'Old WA']);

    $this->put(route('notifications.update', $channel), [
        'name' => 'New WA',
        'driver' => 'whatsapp',
        'whatsapp_api_url' => $channel->config['api_url'],
        'whatsapp_api_token' => '',
        'whatsapp_recipient' => $channel->config['recipient'],
        'notify_on_success' => '1',
        'notify_on_failure' => '1',
        'is_active' => '1',
    ])->assertRedirect(route('notifications.index'));

    expect($channel->fresh()->name)->toBe('New WA');
    expect($channel->fresh()->config['api_token'])->toBe('test-token');
});

it('deletes a notification channel', function () {
    actingAsAdmin();

    $channel = NotificationChannel::factory()->create();

    $this->delete(route('notifications.destroy', $channel))
        ->assertRedirect(route('notifications.index'));

    $this->assertSoftDeleted('notification_channels', ['id' => $channel->id]);
});

it('filters channels by driver', function () {
    actingAsAdmin();

    NotificationChannel::factory()->email()->create(['name' => 'Email Channel']);
    NotificationChannel::factory()->whatsapp()->create(['name' => 'WhatsApp Channel']);

    $this->get(route('notifications.index', ['driver' => 'whatsapp']))
        ->assertOk()
        ->assertSee('WhatsApp Channel')
        ->assertDontSee('Email Channel');
});

it('tests email channel successfully', function () {
    actingAsAdmin();

    Mail::fake();

    $channel = NotificationChannel::factory()->email('admin@example.com')->create();

    $this->post(route('notifications.test', $channel))
        ->assertRedirect()
        ->assertSessionHas('testResult');

    Mail::assertSent(BackupAlertMail::class);
});

it('tests whatsapp channel successfully', function () {
    actingAsAdmin();

    Http::fake([
        'https://api.example.com/*' => Http::response(['status' => true], 200),
    ]);

    $channel = NotificationChannel::factory()->whatsapp()->create();

    $response = $this->post(route('notifications.test', $channel));

    $response->assertRedirect()->assertSessionHas('testResult');
    expect(session('testResult')['success'])->toBeTrue();
});

it('sends notification when backup completes event fires', function () {
    actingAsAdmin();

    Mail::fake();

    NotificationChannel::factory()->email('alert@example.com')->create();
    $history = App\Models\BackupHistory::factory()->success()->create();

    event(new App\Events\Backup\BackupCompleted($history));

    Mail::assertSent(BackupAlertMail::class);
});
