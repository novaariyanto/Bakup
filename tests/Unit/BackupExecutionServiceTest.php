<?php

use App\Enums\BackupHistoryStatus;
use App\Exceptions\BackupExecutionException;
use App\Jobs\Backup\ExecuteBackupJob;
use App\Enums\TableDumpMode;
use App\Models\BackupProfile;
use App\Models\BackupProfileTable;
use App\Services\Backup\BackupExecutionService;
use App\Services\Backup\BackupRuntimeConfigService;
use App\Services\Backup\SpatieBackupRunner;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

it('builds runtime backup config from profile', function () {
    $profile = BackupProfile::factory()->create([
        'name' => 'Production Backup',
        'backup_database' => true,
        'backup_folders' => false,
    ]);

    BackupProfileTable::create([
        'backup_profile_id' => $profile->id,
        'table_name' => 'sessions',
        'dump_mode' => TableDumpMode::StructureOnly,
    ]);

    $profile->load(['databaseConnection', 'destinations', 'excludedTables', 'includeFolders', 'excludeFolders']);

    $runtimeConfig = app(BackupRuntimeConfigService::class)->build($profile);

    expect($runtimeConfig->connectionName)->toBe('bm_profile_'.$profile->id);
    expect($runtimeConfig->onlyDatabase)->toBeTrue();
    expect($runtimeConfig->onlyFiles)->toBeFalse();
    expect($runtimeConfig->databaseConnectionConfig['host'])->toBe($profile->databaseConnection->host);
    expect($runtimeConfig->databaseConnectionConfig['dump']['structure_only_tables'])->toBe(['sessions']);
    expect($runtimeConfig->databaseConnectionConfig['dump']['exclude_tables'] ?? [])->toBe([]);
    expect($runtimeConfig->destinationDiskNames)->toHaveCount(1);
    expect($runtimeConfig->backupConfig['backup']['name'])->toBe($profile->uuid);
    expect($runtimeConfig->backupConfig['backup']['source']['databases'])->toBe([$runtimeConfig->connectionName]);

    if (app(App\Services\Backup\DatabaseDumpBinaryResolver::class)->isGzipAvailable()) {
        expect($runtimeConfig->backupConfig['backup']['database_dump_compressor'])
            ->toBe(App\Support\Backup\Compressors\ResolvableGzipCompressor::class);
    } else {
        expect($runtimeConfig->backupConfig['backup']['database_dump_compressor'])->toBeNull();
    }
});

it('applies runtime config to laravel config', function () {
    $profile = BackupProfile::factory()->create();
    $profile->load(['databaseConnection', 'destinations', 'excludedTables', 'includeFolders', 'excludeFolders']);

    $runtimeConfig = app(BackupRuntimeConfigService::class)->build($profile);
    app(BackupRuntimeConfigService::class)->apply($runtimeConfig);

    expect(config('database.connections.'.$runtimeConfig->connectionName))->not->toBeNull();
    expect(config('filesystems.disks.'.$runtimeConfig->destinationDiskNames[0]))->not->toBeNull();
    expect(config('backup.backup.name'))->toBe($profile->uuid);
});

it('includes routines dump option when stored procedures are enabled', function () {
    $profile = BackupProfile::factory()->create([
        'include_stored_procedures' => true,
        'include_views' => false,
    ]);

    $profile->load(['databaseConnection', 'destinations', 'excludedTables', 'includeFolders', 'excludeFolders']);

    $runtimeConfig = app(BackupRuntimeConfigService::class)->build($profile);

    expect($runtimeConfig->databaseConnectionConfig['dump']['addExtraOption'] ?? null)->toBe('--routines');
    expect($runtimeConfig->databaseConnectionConfig['dump']['includeViews'] ?? null)->toBeNull();
});

it('marks views dump option when views are enabled', function () {
    $profile = BackupProfile::factory()->create([
        'include_stored_procedures' => false,
        'include_views' => true,
    ]);

    $profile->load(['databaseConnection', 'destinations', 'excludedTables', 'includeFolders', 'excludeFolders']);

    $runtimeConfig = app(BackupRuntimeConfigService::class)->build($profile);

    expect($runtimeConfig->databaseConnectionConfig['dump']['includeViews'] ?? null)->toBeTrue();
    expect($runtimeConfig->databaseConnectionConfig['dump']['addExtraOption'] ?? null)->toBeNull();
});

it('builds folder-only runtime config', function () {
    $profile = BackupProfile::factory()->withFolders()->create([
        'backup_database' => false,
        'backup_folders' => true,
    ]);

    $profile->includeFolders()->create(['path' => 'storage/app']);
    $profile->excludeFolders()->create(['path' => 'storage/logs']);
    $profile->load(['databaseConnection', 'destinations', 'excludedTables', 'includeFolders', 'excludeFolders']);

    $runtimeConfig = app(BackupRuntimeConfigService::class)->build($profile);

    expect($runtimeConfig->onlyFiles)->toBeTrue();
    expect($runtimeConfig->backupConfig['backup']['source']['databases'])->toBe([]);
    expect($runtimeConfig->backupConfig['backup']['source']['files']['include'])->not->toBeEmpty();
});

it('dispatches execute backup job', function () {
    Bus::fake();

    $profile = BackupProfile::factory()->create();

    $history = app(BackupExecutionService::class)->dispatch($profile);

    expect($history->status)->toBe(BackupHistoryStatus::Pending);

    Bus::assertDispatched(ExecuteBackupJob::class, function (ExecuteBackupJob $job) use ($profile, $history) {
        return $job->backupProfileId === $profile->id
            && $job->backupHistoryId === $history->id;
    });
});

it('marks history failed when spatie backup command fails', function () {
    $this->mock(SpatieBackupRunner::class, function ($mock): void {
        $mock->shouldReceive('run')
            ->once()
            ->andThrow(BackupExecutionException::spatieFailed('mysqldump not found'));
    });

    $profile = BackupProfile::factory()->create();
    $history = app(App\Services\Backup\BackupHistoryService::class)->createPending($profile);

    try {
        app(BackupExecutionService::class)->execute($profile, $history);
    } catch (Throwable) {
        // expected
    }

    $history->refresh();

    expect($history->status)->toBe(BackupHistoryStatus::Failed);
    expect($history->message)->not->toBeEmpty();
    expect($history->logs()->count())->toBeGreaterThan(0);
    expect($history->logs()->where('level', 'error')->first()?->message)->toContain('mysqldump not found');
});

it('rejects dispatch when profile is inactive', function () {
    $profile = BackupProfile::factory()->inactive()->create();

    expect(fn () => app(BackupExecutionService::class)->dispatch($profile))
        ->toThrow(App\Exceptions\BackupExecutionException::class);
});

it('records backup stages in cache during execution prep', function () {
    $profile = BackupProfile::factory()->create();
    $history = app(App\Services\Backup\BackupHistoryService::class)->createPending($profile);

    app(App\Services\Backup\BackupHistoryService::class)->markRunning($history);
    app(App\Services\Backup\BackupHistoryService::class)->markStage(
        $history,
        App\Enums\BackupStage::Preparing,
        'Test stage',
    );

    expect(Cache::get('backup:'.$history->id.':stage'))->toBe('preparing');
});

it('applies retention after successful backup execution', function () {
    $this->mock(SpatieBackupRunner::class, function ($mock): void {
        $mock->shouldReceive('run')->once();
    });

    $profile = BackupProfile::factory()->create([
        'retention_type' => App\Enums\RetentionType::KeepLast,
        'retention_value' => 1,
    ]);
    $profile->load('destinations');

    $destination = $profile->destinations->first();
    $storagePath = $destination->config['path'] ?? 'test';
    $relativePath = $profile->uuid.'/retention-backup-2026-07-01-03-00-00.zip';
    $diskRoot = storage_path('app/backups/'.$storagePath.'/'.$profile->uuid);

    if (! is_dir($diskRoot)) {
        mkdir($diskRoot, 0755, true);
    }

    file_put_contents(storage_path('app/backups/'.$storagePath.'/'.$relativePath), 'retention-test');

    App\Models\BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'finished_at' => now()->subDay(),
    ]);

    $history = app(App\Services\Backup\BackupHistoryService::class)->createPending($profile);

    app(BackupExecutionService::class)->execute($profile, $history);

    expect(App\Models\BackupHistory::where('backup_profile_id', $profile->id)->count())->toBe(1);
    expect($history->fresh()->status)->toBe(BackupHistoryStatus::Success);
});
