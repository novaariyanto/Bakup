<?php

use App\Jobs\Backup\ExecuteBackupJob;
use App\Models\BackupHistory;
use App\Models\BackupProfile;
use Illuminate\Support\Facades\Bus;

it('dispatches backup execution through queue from service', function () {
    actingAsAdmin();

    Bus::fake();

    $profile = BackupProfile::factory()->create();

    $history = app(App\Services\Backup\BackupExecutionService::class)->dispatch($profile, auth()->id());

    $this->assertDatabaseHas('backup_histories', [
        'id' => $history->id,
        'backup_profile_id' => $profile->id,
        'triggered_by' => auth()->id(),
        'status' => 'pending',
    ]);

    Bus::assertDispatched(ExecuteBackupJob::class);
});

it('prevents duplicate running backups for same profile', function () {
    actingAsAdmin();

    $profile = BackupProfile::factory()->create();
    BackupHistory::factory()->running()->create(['backup_profile_id' => $profile->id]);

    expect(fn () => app(App\Services\Backup\BackupExecutionService::class)->dispatch($profile))
        ->toThrow(App\Exceptions\BackupExecutionException::class);
});
