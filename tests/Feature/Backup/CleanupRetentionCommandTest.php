<?php

use App\Models\BackupHistory;
use App\Models\BackupProfile;
use Illuminate\Support\Facades\Artisan;

it('runs retention cleanup via artisan command', function () {
    $profile = BackupProfile::factory()->create([
        'retention_type' => 'keep_last',
        'retention_value' => 1,
    ]);

    BackupHistory::factory()->success()->count(2)->create(['backup_profile_id' => $profile->id]);

    Artisan::call('backup:cleanup-retention');

    expect(Artisan::output())->toContain('Retention cleanup removed 1 backup record(s).');
    expect(BackupHistory::where('backup_profile_id', $profile->id)->count())->toBe(1);
});

it('registers retention cleanup command in scheduler', function () {
    $events = Illuminate\Support\Facades\Schedule::events();

    expect(collect($events)->contains(fn ($event) => str_contains($event->command ?? '', 'backup:cleanup-retention')))->toBeTrue();
});
