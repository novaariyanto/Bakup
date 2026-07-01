<?php

use App\Jobs\Backup\ExecuteBackupJob;
use App\Models\BackupDestination;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use Illuminate\Support\Facades\Bus;

it('requires authentication to access backup history', function () {
    $this->get(route('backup-history.index'))
        ->assertRedirect(route('login'));
});

it('renders backup history page for administrator', function () {
    actingAsAdmin();

    $this->get(route('backup-history.index'))
        ->assertOk()
        ->assertSee('Backup History');
});

it('lists backup histories with profile name', function () {
    actingAsAdmin();

    $profile = BackupProfile::factory()->create(['name' => 'Prod Backup']);
    BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'filename' => 'prod-backup.zip',
    ]);

    $this->get(route('backup-history.index'))
        ->assertOk()
        ->assertSee('Prod Backup')
        ->assertSee('prod-backup.zip');
});

it('filters histories by status', function () {
    actingAsAdmin();

    $profile = BackupProfile::factory()->create();
    BackupHistory::factory()->success()->create(['backup_profile_id' => $profile->id, 'filename' => 'ok.zip']);
    BackupHistory::factory()->failed()->create(['backup_profile_id' => $profile->id]);

    $this->get(route('backup-history.index', ['status' => 'failed']))
        ->assertOk()
        ->assertSee('Gagal')
        ->assertDontSee('ok.zip');
});

it('filters histories by profile', function () {
    actingAsAdmin();

    $profileA = BackupProfile::factory()->create(['name' => 'Profile Alpha']);
    $profileB = BackupProfile::factory()->create(['name' => 'Profile Beta']);

    BackupHistory::factory()->success()->create(['backup_profile_id' => $profileA->id, 'filename' => 'alpha.zip']);
    BackupHistory::factory()->success()->create(['backup_profile_id' => $profileB->id, 'filename' => 'beta.zip']);

    $this->get(route('backup-history.index', ['profile' => $profileA->id]))
        ->assertOk()
        ->assertSee('alpha.zip')
        ->assertDontSee('beta.zip');
});

it('opens detail modal with progress data', function () {
    actingAsAdmin();

    $history = BackupHistory::factory()->running()->create([
        'current_stage' => 'compressing',
        'metadata' => ['profile_name' => 'Detail Profile'],
    ]);

    $this->get(route('backup-history.index', ['history' => $history->id]))
        ->assertOk()
        ->assertSee('Detail Profile');
});

it('soft deletes backup history record', function () {
    actingAsAdmin();

    $history = BackupHistory::factory()->success()->create();

    $this->delete(route('backup-history.destroy', $history))
        ->assertRedirect(route('backup-history.index'));

    $this->assertSoftDeleted('backup_histories', ['id' => $history->id]);
});

it('retries failed backup and dispatches job', function () {
    actingAsAdmin();

    Bus::fake();

    $profile = BackupProfile::factory()->create();
    $history = BackupHistory::factory()->failed()->create(['backup_profile_id' => $profile->id]);

    $this->post(route('backup-history.retry', $history))
        ->assertRedirect();

    Bus::assertDispatched(ExecuteBackupJob::class);
    expect(BackupHistory::where('backup_profile_id', $profile->id)->count())->toBe(2);
});

it('denies retry for successful backup', function () {
    actingAsAdmin();

    $history = BackupHistory::factory()->success()->create();

    $this->post(route('backup-history.retry', $history))
        ->assertForbidden();
});

it('downloads successful backup file', function () {
    actingAsAdmin();

    $storagePath = 'history-download-test';
    $destination = BackupDestination::factory()->local($storagePath)->create();
    $profile = BackupProfile::factory()->create();
    $profile->destinations()->sync([$destination->id => ['sort_order' => 0]]);

    $relativePath = $profile->uuid.'/test-backup.zip';
    $diskRoot = storage_path('app/backups/'.$storagePath.'/'.$profile->uuid);
    if (! is_dir($diskRoot)) {
        mkdir($diskRoot, 0755, true);
    }

    file_put_contents(storage_path('app/backups/'.$storagePath.'/'.$relativePath), 'backup-content-test');

    $history = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'filename' => 'test-backup.zip',
        'metadata' => ['storage_path' => $relativePath],
    ]);

    $this->get(route('backup-history.download', $history))
        ->assertOk()
        ->assertDownload('test-backup.zip');
});

it('redirects with error when backup file is missing', function () {
    actingAsAdmin();

    $history = BackupHistory::factory()->success()->create([
        'filename' => 'missing-backup.zip',
        'metadata' => ['storage_path' => 'missing/missing-backup.zip'],
    ]);

    $this->get(route('backup-history.download', $history))
        ->assertRedirect(route('backup-history.index'))
        ->assertSessionHas('error', 'File backup tidak ditemukan di storage.');
});

it('denies download for failed backup', function () {
    actingAsAdmin();

    $history = BackupHistory::factory()->failed()->create(['filename' => 'missing.zip']);

    $this->get(route('backup-history.download', $history))
        ->assertForbidden();
});

it('deletes backup file from local storage when history is deleted', function () {
    actingAsAdmin();

    $storagePath = 'history-delete-test';
    $destination = BackupDestination::factory()->local($storagePath)->create();
    $profile = BackupProfile::factory()->create();
    $profile->destinations()->sync([$destination->id => ['sort_order' => 0]]);

    $relativePath = $profile->uuid.'/delete-me.zip';
    $diskRoot = storage_path('app/backups/'.$storagePath.'/'.$profile->uuid);
    if (! is_dir($diskRoot)) {
        mkdir($diskRoot, 0755, true);
    }

    $filePath = storage_path('app/backups/'.$storagePath.'/'.$relativePath);
    file_put_contents($filePath, 'delete-content');

    $history = BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'filename' => 'delete-me.zip',
        'metadata' => ['storage_path' => $relativePath],
    ]);

    $this->delete(route('backup-history.destroy', $history))
        ->assertRedirect(route('backup-history.index'));

    expect(file_exists($filePath))->toBeFalse();
});
