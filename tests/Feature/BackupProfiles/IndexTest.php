<?php

use App\Jobs\Backup\ExecuteBackupJob;
use App\Models\BackupDestination;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\Bus;

it('requires authentication to access backup profiles', function () {
    $this->get(route('backup-profiles.index'))
        ->assertRedirect(route('login'));
});

it('renders backup profiles page for administrator', function () {
    actingAsAdmin();

    $this->get(route('backup-profiles.index'))
        ->assertOk()
        ->assertSee('Backup Profiles');
});

it('renders load tables action on create page', function () {
    actingAsAdmin();

    $this->get(route('backup-profiles.create'))
        ->assertOk()
        ->assertSee('Muat Tabel')
        ->assertSee('Mode Backup Tabel')
        ->assertSee('Structure Only')
        ->assertSee('activity_log, sessions');
});

it('shows validation errors when create form is incomplete', function () {
    actingAsAdmin();

    $this->from(route('backup-profiles.create'))
        ->post(route('backup-profiles.store'), [
            'backup_database' => '0',
            'backup_folders' => '0',
        ])
        ->assertRedirect(route('backup-profiles.create'))
        ->assertSessionHasErrors(['name', 'database_connection_id', 'selected_destination_ids', 'backup_database']);
});

it('returns database tables for selected connection', function () {
    actingAsAdmin();

    $connection = DatabaseConnection::factory()->create();

    $this->mock(App\Services\Backup\BackupProfileService::class, function ($mock): void {
        $mock->shouldReceive('fetchTablesForConnection')
            ->once()
            ->andReturn([
                new App\DTO\DatabaseTableInfo('users', 'InnoDB', 10, '16 KB'),
                new App\DTO\DatabaseTableInfo('sessions', 'InnoDB', 2, '8 KB'),
            ]);
    });

    $this->getJson(route('backup-profiles.tables', $connection))
        ->assertOk()
        ->assertJsonPath('tables.0.name', 'users')
        ->assertJsonPath('tables.1.name', 'sessions');
});

it('returns validation error when tables endpoint fails', function () {
    actingAsAdmin();

    $connection = DatabaseConnection::factory()->create();

    $this->mock(App\Services\Backup\BackupProfileService::class, function ($mock): void {
        $mock->shouldReceive('fetchTablesForConnection')
            ->once()
            ->andThrow(App\Exceptions\BackupProfileException::tablesFetchFailed('Connection refused'));
    });

    $this->getJson(route('backup-profiles.tables', $connection))
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'Connection refused'));
});

function backupProfilePayload(array $overrides = []): array
{
    return array_merge([
        'backup_database' => '1',
        'backup_folders' => '0',
        'compression' => 'gzip',
        'schedule_type' => 'daily',
        'schedule_time' => '03:00',
        'retention_type' => 'keep_last',
        'retention_value' => 14,
        'is_active' => '1',
    ], $overrides);
}

it('creates a backup profile with destinations', function () {
    actingAsAdmin();

    $connection = DatabaseConnection::factory()->create(['name' => 'Main DB']);
    $destination = BackupDestination::factory()->create(['name' => 'Local Storage']);

    $this->post(route('backup-profiles.store'), backupProfilePayload([
        'name' => 'Daily Backup',
        'description' => 'Backup harian production',
        'database_connection_id' => $connection->id,
        'selected_destination_ids' => [$destination->id],
    ]))->assertRedirect(route('backup-profiles.index'));

    $profile = BackupProfile::where('name', 'Daily Backup')->first();

    expect($profile)->not->toBeNull();
    expect($profile->database_connection_id)->toBe($connection->id);
    expect($profile->destinations)->toHaveCount(1);
    expect($profile->schedule_type->value)->toBe('daily');
});

it('creates a profile with structure only tables', function () {
    actingAsAdmin();

    $connection = DatabaseConnection::factory()->create();
    $destination = BackupDestination::factory()->create();

    $this->post(route('backup-profiles.store'), backupProfilePayload([
        'name' => 'Structure Only Profile',
        'database_connection_id' => $connection->id,
        'table_dump_modes' => [
            'activity_log' => 'structure_only',
            'backup_destinations' => 'structure_only',
            'backup_histories' => 'structure_only',
        ],
        'selected_destination_ids' => [$destination->id],
    ]))->assertRedirect(route('backup-profiles.index'));

    $profile = BackupProfile::where('name', 'Structure Only Profile')->first();

    expect($profile->excludedTables->pluck('table_name')->all())
        ->toEqualCanonicalizing(['activity_log', 'backup_destinations', 'backup_histories']);
    expect($profile->excludedTables->every(fn ($table) => $table->dump_mode->value === 'structure_only'))->toBeTrue();
});

it('creates a profile with structure only tables and folders', function () {
    actingAsAdmin();

    $connection = DatabaseConnection::factory()->create();
    $destination = BackupDestination::factory()->create();

    $this->post(route('backup-profiles.store'), backupProfilePayload([
        'name' => 'Full Backup',
        'database_connection_id' => $connection->id,
        'backup_folders' => '1',
        'include_folders' => ['storage/app'],
        'exclude_folders' => ['storage/logs'],
        'table_dump_modes' => [
            'sessions' => 'structure_only',
            'cache' => 'structure_only',
        ],
        'selected_destination_ids' => [$destination->id],
    ]))->assertRedirect(route('backup-profiles.index'));

    $profile = BackupProfile::where('name', 'Full Backup')->first();

    expect($profile->excludedTables->pluck('table_name')->all())->toEqualCanonicalizing(['sessions', 'cache']);
    expect($profile->includeFolders->pluck('path')->all())->toBe(['storage/app']);
    expect($profile->excludeFolders->pluck('path')->all())->toBe(['storage/logs']);
});

it('updates a backup profile', function () {
    actingAsAdmin();

    $profile = BackupProfile::factory()->create(['name' => 'Old Profile']);

    $this->put(route('backup-profiles.update', $profile), backupProfilePayload([
        'name' => 'Updated Profile',
        'database_connection_id' => $profile->database_connection_id,
        'selected_destination_ids' => [$profile->destinations->first()->id],
    ]))->assertRedirect(route('backup-profiles.index'));

    expect($profile->fresh()->name)->toBe('Updated Profile');
});

it('deletes a backup profile', function () {
    actingAsAdmin();

    $profile = BackupProfile::factory()->create();

    $this->delete(route('backup-profiles.destroy', $profile))
        ->assertRedirect(route('backup-profiles.index'));

    $this->assertSoftDeleted('backup_profiles', ['id' => $profile->id]);
});

it('filters profiles by search query', function () {
    actingAsAdmin();

    BackupProfile::factory()->create(['name' => 'Alpha Profile']);
    BackupProfile::factory()->create(['name' => 'Beta Profile']);

    $this->get(route('backup-profiles.index', ['q' => 'Alpha']))
        ->assertOk()
        ->assertSee('Alpha Profile')
        ->assertDontSee('Beta Profile');
});

it('filters profiles by connection', function () {
    actingAsAdmin();

    $connectionA = DatabaseConnection::factory()->create(['name' => 'Conn A']);
    $connectionB = DatabaseConnection::factory()->create(['name' => 'Conn B']);

    BackupProfile::factory()->create(['name' => 'Profile A', 'database_connection_id' => $connectionA->id]);
    BackupProfile::factory()->create(['name' => 'Profile B', 'database_connection_id' => $connectionB->id]);

    $this->get(route('backup-profiles.index', ['connection' => $connectionA->id]))
        ->assertOk()
        ->assertSee('Profile A')
        ->assertDontSee('Profile B');
});

it('filters profiles by active status', function () {
    actingAsAdmin();

    BackupProfile::factory()->create(['name' => 'Active Profile', 'is_active' => true]);
    BackupProfile::factory()->inactive()->create(['name' => 'Inactive Profile']);

    $this->get(route('backup-profiles.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('Inactive Profile')
        ->assertDontSee('Active Profile');
});

it('starts manual backup and opens progress modal', function () {
    actingAsAdmin();

    Bus::fake();

    $profile = BackupProfile::factory()->create(['name' => 'Manual Profile']);

    $this->post(route('backup-profiles.run', $profile))
        ->assertRedirect()
        ->assertSessionHas('progress_data');

    Bus::assertDispatched(ExecuteBackupJob::class);

    expect(BackupHistory::where('backup_profile_id', $profile->id)->exists())->toBeTrue();
});

it('refreshes progress data for running backup', function () {
    actingAsAdmin();

    $profile = BackupProfile::factory()->create();
    $history = BackupHistory::factory()->running()->create([
        'backup_profile_id' => $profile->id,
        'current_stage' => 'dumping_database',
        'metadata' => ['profile_name' => 'Test Profile'],
    ]);

    $this->get(route('backup-profiles.index', ['progress' => $history->id]))
        ->assertOk()
        ->assertSee('Test Profile')
        ->assertSee('Status diperbarui otomatis');
});

it('returns progress json for backup history', function () {
    actingAsAdmin();

    $profile = BackupProfile::factory()->create();
    $history = BackupHistory::factory()->running()->create([
        'backup_profile_id' => $profile->id,
        'current_stage' => 'compressing',
        'metadata' => ['profile_name' => 'JSON Profile'],
    ]);

    $this->getJson(route('backup-profiles.progress', $history))
        ->assertOk()
        ->assertJsonPath('profile_name', 'JSON Profile')
        ->assertJsonPath('status', 'running')
        ->assertJsonPath('is_finished', false);
});

it('denies run backup for inactive profile', function () {
    actingAsAdmin();

    $profile = BackupProfile::factory()->inactive()->create();

    $this->post(route('backup-profiles.run', $profile))
        ->assertForbidden();
});

it('shows error when backup already running for profile', function () {
    actingAsAdmin();

    $profile = BackupProfile::factory()->create();
    BackupHistory::factory()->running()->create(['backup_profile_id' => $profile->id]);

    $this->post(route('backup-profiles.run', $profile))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(BackupHistory::where('backup_profile_id', $profile->id)->count())->toBe(1);
});
