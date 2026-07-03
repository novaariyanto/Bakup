<?php

use App\Enums\RetentionType;
use App\Enums\ScheduleType;
use App\Exceptions\BackupProfileException;
use App\Models\BackupDestination;
use App\Models\BackupProfile;
use App\Models\DatabaseConnection;
use App\Repositories\BackupProfileRepository;
use App\Services\Backup\BackupProfileService;
use App\Services\Schedule\ScheduleService;
use App\Support\BackupLogger;

it('paginates profiles with search filter', function () {
    BackupProfile::factory()->create(['name' => 'Find Me Profile']);
    BackupProfile::factory()->create(['name' => 'Other Profile']);

    $results = app(BackupProfileRepository::class)->paginate(search: 'Find');

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Find Me Profile');
});

it('creates profile with synced relations', function () {
    $connection = DatabaseConnection::factory()->create();
    $destination = BackupDestination::factory()->create();

    $profile = app(BackupProfileService::class)->create([
        'name' => 'Service Profile',
        'database_connection_id' => $connection->id,
        'backup_database' => true,
        'backup_folders' => true,
        'compression' => 'gzip',
        'schedule_type' => ScheduleType::Manual->value,
        'retention_type' => RetentionType::KeepLast->value,
        'retention_value' => 5,
        'is_active' => true,
        'destination_ids' => [$destination->id],
        'include_folders' => ['storage/app'],
        'exclude_folders' => ['vendor'],
        'table_dump_modes' => [
            'migrations' => 'structure_only',
        ],
    ]);

    expect($profile->destinations)->toHaveCount(1);
    expect($profile->includeFolders->pluck('path')->all())->toBe(['storage/app']);
    expect($profile->excludeFolders->pluck('path')->all())->toBe(['vendor']);
    expect($profile->excludedTables->pluck('table_name')->all())->toBe(['migrations']);
    expect($profile->excludedTables->first()->dump_mode->value)->toBe('structure_only');
});

it('requires at least one backup type', function () {
    $connection = DatabaseConnection::factory()->create();
    $destination = BackupDestination::factory()->create();

    expect(fn () => app(BackupProfileService::class)->create([
        'name' => 'Invalid',
        'database_connection_id' => $connection->id,
        'backup_database' => false,
        'backup_folders' => false,
        'compression' => 'gzip',
        'schedule_type' => ScheduleType::Manual->value,
        'retention_type' => RetentionType::KeepLast->value,
        'retention_value' => 5,
        'destination_ids' => [$destination->id],
    ]))->toThrow(BackupProfileException::class);
});

it('requires at least one destination', function () {
    $connection = DatabaseConnection::factory()->create();

    expect(fn () => app(BackupProfileService::class)->create([
        'name' => 'No Destination',
        'database_connection_id' => $connection->id,
        'backup_database' => true,
        'backup_folders' => false,
        'compression' => 'gzip',
        'schedule_type' => ScheduleType::Manual->value,
        'retention_type' => RetentionType::KeepLast->value,
        'retention_value' => 5,
        'destination_ids' => [],
    ]))->toThrow(BackupProfileException::class);
});

it('logs backup profile lifecycle events', function () {
    $connection = DatabaseConnection::factory()->create();
    $destination = BackupDestination::factory()->create();

    $logger = Mockery::mock(BackupLogger::class);
    $logger->shouldReceive('info')->once();

    $service = new BackupProfileService(
        app(BackupProfileRepository::class),
        app(App\Services\Database\DatabaseConnectionService::class),
        app(ScheduleService::class),
        $logger,
    );

    $service->create([
        'name' => 'Logged Profile',
        'database_connection_id' => $connection->id,
        'backup_database' => true,
        'backup_folders' => false,
        'compression' => 'gzip',
        'schedule_type' => ScheduleType::Manual->value,
        'retention_type' => RetentionType::KeepLast->value,
        'retention_value' => 5,
        'destination_ids' => [$destination->id],
    ]);
});
