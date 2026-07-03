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

it('resolves backup file using stored storage root when destination path changed', function () {
    $destination = BackupDestination::factory()->local('legacy-path')->create();
    $profile = BackupProfile::factory()->create(['name' => 'Legacy Backup']);
    $profile->destinations()->sync([$destination->id => ['sort_order' => 0]]);

    $relativePath = $profile->uuid.'/legacy-backup-2026-07-01-03-00-00.zip';
    $legacyRoot = storage_path('app/backups/legacy-path');
    if (! is_dir($legacyRoot.'/'.$profile->uuid)) {
        mkdir($legacyRoot.'/'.$profile->uuid, 0755, true);
    }
    file_put_contents($legacyRoot.'/'.$relativePath, 'zip-content');

    $destination->update(['config' => ['path' => 'new-path']]);

    $history = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'filename' => 'legacy-backup-2026-07-01-03-00-00.zip',
        'metadata' => [
            'destination_id' => $destination->id,
            'storage_path' => $relativePath,
            'storage_root' => $legacyRoot,
        ],
    ]);

    $location = app(BackupFileService::class)->resolveFileLocation($history);

    expect($location)->not->toBeNull();
    expect(str_replace('\\', '/', $location['absolute_path'] ?? ''))
        ->toBe(str_replace('\\', '/', $legacyRoot.'/'.$relativePath));
});

it('finds backup file after destination is detached from profile', function () {
    $storagePath = 'detached-destination-test';
    $destination = BackupDestination::factory()->local($storagePath)->create();
    $profile = BackupProfile::factory()->create(['name' => 'Detached Backup']);

    $relativePath = $profile->uuid.'/detached-backup-2026-07-01-03-00-00.zip';
    $diskRoot = storage_path('app/backups/'.$storagePath.'/'.$profile->uuid);
    if (! is_dir($diskRoot)) {
        mkdir($diskRoot, 0755, true);
    }
    file_put_contents(storage_path('app/backups/'.$storagePath.'/'.$relativePath), 'zip-content');

    $history = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'filename' => 'detached-backup-2026-07-01-03-00-00.zip',
        'metadata' => [
            'destination_id' => $destination->id,
            'storage_path' => $relativePath,
            'storage_root' => storage_path('app/backups/'.$storagePath),
        ],
    ]);

    $profile->destinations()->sync([]);

    $location = app(BackupFileService::class)->resolveFileLocation($history);

    expect($location)->not->toBeNull();
});
