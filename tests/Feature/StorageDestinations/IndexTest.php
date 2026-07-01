<?php

use App\Models\BackupDestination;

it('requires authentication to access storage destinations', function () {
    $this->get(route('storage-destinations.index'))
        ->assertRedirect(route('login'));
});

it('renders storage destinations page for administrator', function () {
    actingAsAdmin();

    $this->get(route('storage-destinations.index'))
        ->assertOk()
        ->assertSee('Storage Destinations');
});

it('creates a local storage destination', function () {
    actingAsAdmin();

    $this->post(route('storage-destinations.store'), [
        'name' => 'Local Backups',
        'driver' => 'local',
        'local_path' => 'feature-test',
        'is_active' => '1',
    ])->assertRedirect(route('storage-destinations.index'));

    $this->assertDatabaseHas('backup_destinations', [
        'name' => 'Local Backups',
        'driver' => 'local',
        'is_active' => true,
    ]);
});

it('updates a storage destination without changing secret', function () {
    actingAsAdmin();

    $destination = BackupDestination::factory()->s3()->create([
        'name' => 'Old S3',
    ]);

    $this->put(route('storage-destinations.update', $destination), [
        'name' => 'New S3',
        'driver' => 's3',
        's3_key' => $destination->config['key'],
        's3_secret' => '',
        's3_bucket' => $destination->config['bucket'],
        's3_region' => $destination->config['region'],
        'is_active' => '1',
    ])->assertRedirect(route('storage-destinations.index'));

    expect($destination->fresh()->name)->toBe('New S3');
    expect($destination->fresh()->config['secret'])->toBe('test-secret');
});

it('deletes a storage destination', function () {
    actingAsAdmin();

    $destination = BackupDestination::factory()->create();

    $this->delete(route('storage-destinations.destroy', $destination))
        ->assertRedirect(route('storage-destinations.index'));

    $this->assertSoftDeleted('backup_destinations', ['id' => $destination->id]);
});

it('filters destinations by search query', function () {
    actingAsAdmin();

    BackupDestination::factory()->create(['name' => 'Alpha Storage']);
    BackupDestination::factory()->create(['name' => 'Beta Storage']);

    $this->get(route('storage-destinations.index', ['q' => 'Alpha']))
        ->assertOk()
        ->assertSee('Alpha Storage')
        ->assertDontSee('Beta Storage');
});

it('filters destinations by driver', function () {
    actingAsAdmin();

    BackupDestination::factory()->local('alpha')->create(['name' => 'Local Alpha']);
    BackupDestination::factory()->s3()->create(['name' => 'S3 Beta']);

    $this->get(route('storage-destinations.index', ['driver' => 'local']))
        ->assertOk()
        ->assertSee('Local Alpha')
        ->assertDontSee('S3 Beta');
});

it('filters destinations by active status', function () {
    actingAsAdmin();

    BackupDestination::factory()->create(['name' => 'Active Storage', 'is_active' => true]);
    BackupDestination::factory()->inactive()->create(['name' => 'Inactive Storage']);

    $this->get(route('storage-destinations.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('Inactive Storage')
        ->assertDontSee('Active Storage');
});

it('tests local storage destination successfully', function () {
    actingAsAdmin();

    $path = 'pest-test-'.uniqid();
    $destination = BackupDestination::factory()->local($path)->create();

    $this->post(route('storage-destinations.test', $destination))
        ->assertRedirect()
        ->assertSessionHas('testResult');

    expect($destination->fresh()->last_test_status)->toBe('success');
});

it('creates sftp destination with password authentication', function () {
    actingAsAdmin();

    $this->post(route('storage-destinations.store'), [
        'name' => 'Remote SFTP',
        'driver' => 'sftp',
        'sftp_host' => '10.0.0.10',
        'sftp_port' => 22,
        'sftp_username' => 'backup',
        'sftp_auth_method' => 'password',
        'sftp_password' => 'secret',
        'sftp_root' => '/backups',
        'is_active' => '1',
    ])->assertRedirect(route('storage-destinations.index'));

    $this->assertDatabaseHas('backup_destinations', [
        'name' => 'Remote SFTP',
        'driver' => 'sftp',
    ]);

    $destination = BackupDestination::where('name', 'Remote SFTP')->first();
    expect($destination->config['auth_method'])->toBe('password');
    expect($destination->config)->toHaveKey('password');
    expect($destination->config)->not->toHaveKey('private_key');
});

it('rejects sftp create when password auth selected without password', function () {
    actingAsAdmin();

    $this->post(route('storage-destinations.store'), [
        'name' => 'Bad SFTP',
        'driver' => 'sftp',
        'sftp_host' => '10.0.0.10',
        'sftp_port' => 22,
        'sftp_username' => 'backup',
        'sftp_auth_method' => 'password',
        'sftp_root' => '/backups',
        'is_active' => '1',
    ])->assertSessionHasErrors('sftp_password');
});
