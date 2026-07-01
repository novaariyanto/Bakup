<?php

use App\Enums\ScheduleType;
use App\Models\BackupProfile;
use App\Services\Schedule\ScheduleService;
use Illuminate\Support\Carbon;

it('returns null next run for manual profiles', function () {
    $profile = BackupProfile::factory()->create([
        'schedule_type' => ScheduleType::Manual,
    ]);

    expect(app(ScheduleService::class)->calculateNextRunAt($profile))->toBeNull();
});

it('calculates next hourly run', function () {
    Carbon::setTestNow('2026-07-01 10:45:00');

    $profile = BackupProfile::factory()->hourly()->create();

    $next = app(ScheduleService::class)->calculateNextRunAt($profile);

    expect($next?->format('Y-m-d H:i:s'))->toBe('2026-07-01 11:00:00');
});

it('calculates next daily run', function () {
    Carbon::setTestNow('2026-07-01 10:00:00');

    $profile = BackupProfile::factory()->daily('03:00')->create();

    $next = app(ScheduleService::class)->calculateNextRunAt($profile);

    expect($next?->format('Y-m-d H:i:s'))->toBe('2026-07-02 03:00:00');
});

it('calculates next weekly run', function () {
    Carbon::setTestNow('2026-07-01 10:00:00'); // Wednesday

    $profile = BackupProfile::factory()->weekly(dayOfWeek: 1, time: '03:00')->create();

    $next = app(ScheduleService::class)->calculateNextRunAt($profile);

    expect($next?->format('Y-m-d H:i:s'))->toBe('2026-07-06 03:00:00');
});

it('calculates next cron run', function () {
    Carbon::setTestNow('2026-07-01 10:00:00');

    $profile = BackupProfile::factory()->cron('0 2 * * *')->create();

    $next = app(ScheduleService::class)->calculateNextRunAt($profile);

    expect($next?->format('Y-m-d H:i:s'))->toBe('2026-07-02 02:00:00');
});

it('syncs next run when profile is created', function () {
    Carbon::setTestNow('2026-07-01 08:00:00');

    $profile = app(App\Services\Backup\BackupProfileService::class)->create([
        'name' => 'Scheduled Profile',
        'database_connection_id' => App\Models\DatabaseConnection::factory()->create()->id,
        'backup_database' => true,
        'backup_folders' => false,
        'compression' => 'gzip',
        'schedule_type' => ScheduleType::Daily->value,
        'schedule_time' => '09:00',
        'retention_type' => 'keep_last',
        'retention_value' => 7,
        'destination_ids' => [App\Models\BackupDestination::factory()->create()->id],
    ]);

    expect($profile->next_run_at?->format('Y-m-d H:i:s'))->toBe('2026-07-01 09:00:00');
});

it('marks profile as due when next run is in the past', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    $profile = BackupProfile::factory()->daily('03:00')->dueNow()->create();

    expect(app(ScheduleService::class)->isDue($profile))->toBeTrue();
});

it('does not mark manual profiles as due', function () {
    $profile = BackupProfile::factory()->create([
        'schedule_type' => ScheduleType::Manual,
        'next_run_at' => now()->subMinute(),
    ]);

    expect(app(ScheduleService::class)->isDue($profile))->toBeFalse();
});

it('advances next run after scheduled dispatch', function () {
    Carbon::setTestNow('2026-07-01 03:00:00');
    Illuminate\Support\Facades\Bus::fake();

    $profile = BackupProfile::factory()->daily('03:00')->dueNow()->create();

    $processed = app(ScheduleService::class)->processDueProfiles();

    expect($processed)->toBe(1);
    expect($profile->fresh()->last_scheduled_run_at?->format('Y-m-d H:i:s'))->toBe('2026-07-01 03:00:00');
    expect($profile->fresh()->next_run_at?->format('Y-m-d H:i:s'))->toBe('2026-07-02 03:00:00');
});
