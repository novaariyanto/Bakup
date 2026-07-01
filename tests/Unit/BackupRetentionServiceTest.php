<?php

use App\Enums\RetentionType;
use App\Models\BackupDestination;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Services\Backup\BackupRetentionService;
use Illuminate\Support\Carbon;

it('keeps only the last n successful backups for keep_last policy', function () {
    $profile = BackupProfile::factory()->create([
        'retention_type' => RetentionType::KeepLast,
        'retention_value' => 2,
    ]);

    $keepA = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now()->subHours(1),
        'filename' => 'keep-a.zip',
    ]);
    $keepB = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now(),
        'filename' => 'keep-b.zip',
    ]);
    $remove = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now()->subDays(2),
        'filename' => 'remove.zip',
    ]);

    $deleted = app(BackupRetentionService::class)->applyForProfile($profile);

    expect($deleted)->toBe(1);
    expect(BackupHistory::find($keepA->id))->not->toBeNull();
    expect(BackupHistory::find($keepB->id))->not->toBeNull();
    expect(BackupHistory::withTrashed()->find($remove->id)?->trashed())->toBeTrue();
});

it('does not delete failed or running backups when applying keep_last', function () {
    $profile = BackupProfile::factory()->create([
        'retention_type' => RetentionType::KeepLast,
        'retention_value' => 1,
    ]);

    BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now(),
    ]);
    $failed = BackupHistory::factory()->failed()->create(['backup_profile_id' => $profile->id]);
    $running = BackupHistory::factory()->running()->create(['backup_profile_id' => $profile->id]);

    app(BackupRetentionService::class)->applyForProfile($profile);

    expect(BackupHistory::find($failed->id))->not->toBeNull();
    expect(BackupHistory::find($running->id))->not->toBeNull();
});

it('deletes successful backups older than retention days', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    $profile = BackupProfile::factory()->create([
        'retention_type' => RetentionType::DeleteOlderThanDays,
        'retention_value' => 7,
    ]);

    $recent = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now()->subDays(3),
        'filename' => 'recent.zip',
    ]);
    $expired = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now()->subDays(10),
        'filename' => 'expired.zip',
    ]);

    $deleted = app(BackupRetentionService::class)->applyForProfile($profile);

    expect($deleted)->toBe(1);
    expect(BackupHistory::find($recent->id))->not->toBeNull();
    expect(BackupHistory::withTrashed()->find($expired->id)?->trashed())->toBeTrue();
});

it('deletes backup files from storage during retention cleanup', function () {
    $storagePath = 'retention-cleanup-test';
    $destination = BackupDestination::factory()->local($storagePath)->create();
    $profile = BackupProfile::factory()->create([
        'retention_type' => RetentionType::KeepLast,
        'retention_value' => 1,
    ]);
    $profile->destinations()->sync([$destination->id => ['sort_order' => 0]]);

    $diskRoot = storage_path('app/backups/'.$storagePath);
    if (! is_dir($diskRoot)) {
        mkdir($diskRoot, 0755, true);
    }

    $oldFile = $diskRoot.'/old-backup.zip';
    file_put_contents($oldFile, 'old-content');

    BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now(),
        'filename' => 'new-backup.zip',
    ]);
    BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now()->subDay(),
        'filename' => 'old-backup.zip',
    ]);

    app(BackupRetentionService::class)->applyForProfile($profile);

    expect(file_exists($oldFile))->toBeFalse();
});

it('applies retention for all active profiles via service', function () {
    $profileA = BackupProfile::factory()->create([
        'retention_type' => RetentionType::KeepLast,
        'retention_value' => 1,
        'is_active' => true,
    ]);
    $profileB = BackupProfile::factory()->inactive()->create([
        'retention_type' => RetentionType::KeepLast,
        'retention_value' => 1,
    ]);

    BackupHistory::factory()->success()->count(2)->create(['backup_profile_id' => $profileA->id]);
    BackupHistory::factory()->success()->count(2)->create(['backup_profile_id' => $profileB->id]);

    $deleted = app(BackupRetentionService::class)->applyForAllProfiles();

    expect($deleted)->toBe(1);
    expect(BackupHistory::where('backup_profile_id', $profileA->id)->count())->toBe(1);
    expect(BackupHistory::where('backup_profile_id', $profileB->id)->count())->toBe(2);
});
