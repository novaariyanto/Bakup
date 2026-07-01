<?php

use App\Models\BackupDestination;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Services\Backup\BackupFileService;

it('resolves spatie backup file inside profile uuid directory', function () {
    $storagePath = 'spatie-download-test';
    $destination = BackupDestination::factory()->local($storagePath)->create();
    $profile = BackupProfile::factory()->create(['name' => 'Production Backup']);
    $profile->destinations()->sync([$destination->id => ['sort_order' => 0]]);

    $diskRoot = storage_path('app/backups/'.$storagePath.'/'.$profile->uuid);
    if (! is_dir($diskRoot)) {
        mkdir($diskRoot, 0755, true);
    }

    $relativePath = $profile->uuid.'/production-backup-2026-07-01-03-00-00.zip';
    file_put_contents(storage_path('app/backups/'.$storagePath.'/'.$relativePath), 'zip-content');

    $history = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'filename' => 'production-backup-2026-07-01-03-00-00.zip',
        'metadata' => ['storage_path' => $relativePath],
    ]);

    $location = app(BackupFileService::class)->resolveFileLocation($history);

    expect($location)->not->toBeNull();
    expect($location['path'])->toBe($relativePath);
});

it('finds latest zip backup when storage path metadata is missing', function () {
    $storagePath = 'spatie-fallback-test';
    $destination = BackupDestination::factory()->local($storagePath)->create();
    $profile = BackupProfile::factory()->create(['name' => 'Fallback Backup']);
    $profile->destinations()->sync([$destination->id => ['sort_order' => 0]]);

    $diskRoot = storage_path('app/backups/'.$storagePath.'/'.$profile->uuid);
    if (! is_dir($diskRoot)) {
        mkdir($diskRoot, 0755, true);
    }

    $relativePath = $profile->uuid.'/fallback-backup-2026-07-01-03-00-00.zip';
    file_put_contents(storage_path('app/backups/'.$storagePath.'/'.$relativePath), 'zip-content');

    $history = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'filename' => 'fallback-backup-2026-07-01-03-00-00.zip',
        'metadata' => [],
    ]);

    $location = app(BackupFileService::class)->resolveFileLocation($history);

    expect($location)->not->toBeNull();
    expect($location['path'])->toBe($relativePath);
});
