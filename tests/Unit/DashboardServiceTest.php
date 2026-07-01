<?php

use App\Enums\BackupHistoryStatus;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Services\Dashboard\DashboardService;
use Illuminate\Support\Carbon;

it('returns dashboard stats from database', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    BackupProfile::factory()->create(['name' => 'Active Profile', 'is_active' => true]);
    BackupProfile::factory()->inactive()->create(['name' => 'Inactive Profile']);

    $profile = BackupProfile::factory()->daily('03:00')->create([
        'name' => 'Scheduled Profile',
        'next_run_at' => now()->addHours(2),
    ]);

    BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now()->subDay(),
        'compressed_size_bytes' => 1048576,
        'filename' => 'backup-a.zip',
    ]);

    BackupHistory::factory()->failed()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now()->subDays(3),
    ]);

    BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now()->subDays(40),
        'compressed_size_bytes' => 512000,
    ]);

    $overview = app(DashboardService::class)->getOverview();

    expect($overview['stats']['profiles'])->toBe(2);
    expect($overview['stats']['profiles_total'])->toBe(3);
    expect($overview['stats']['backups_total'])->toBe(3);
    expect($overview['stats']['backups_success'])->toBe(1);
    expect($overview['stats']['backups_failed'])->toBe(1);
    expect($overview['stats']['next_backup_profile'])->toBe('Scheduled Profile');
    expect($overview['stats']['last_backup_profile'])->toBe('Scheduled Profile');
    expect($overview['stats']['storage_used'])->toBe(1560576);
    expect($overview['stats']['storage_used_label'])->toBe('1.49 MB');
});

it('builds activity chart for the last 30 days', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    BackupHistory::factory()->success()->create(['finished_at' => now()->subDays(2)]);
    BackupHistory::factory()->failed()->create(['finished_at' => now()->subDays(2)]);

    $overview = app(DashboardService::class)->getOverview();

    expect($overview['activity_chart'])->toHaveCount(30);
    expect(collect($overview['activity_chart'])->sum('total'))->toBe(2);

    $targetDay = collect($overview['activity_chart'])->firstWhere('date', now()->subDays(2)->toDateString());
    expect($targetDay['success'])->toBe(1);
    expect($targetDay['failed'])->toBe(1);
});

it('returns recent backup activity ordered by latest', function () {
    $profile = BackupProfile::factory()->create(['name' => 'Recent Profile']);

    $older = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'created_at' => now()->subDays(2),
        'finished_at' => now()->subDays(2),
        'filename' => 'old.zip',
    ]);

    $newer = BackupHistory::factory()->failed()->create([
        'backup_profile_id' => $profile->id,
        'created_at' => now()->subHour(),
        'finished_at' => now()->subHour(),
        'message' => 'Timeout',
    ]);

    $overview = app(DashboardService::class)->getOverview();

    expect($overview['recent_activity'][0]['id'])->toBe($newer->id);
    expect($overview['recent_activity'][0]['profile_name'])->toBe('Recent Profile');
    expect($overview['recent_activity'][0]['status'])->toBe(BackupHistoryStatus::Failed->value);
    expect($overview['recent_activity'][1]['id'])->toBe($older->id);
});
