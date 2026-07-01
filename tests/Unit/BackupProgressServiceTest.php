<?php

use App\Enums\BackupHistoryStatus;
use App\Enums\BackupStage;
use App\Models\BackupHistory;
use App\Services\Backup\BackupProgressService;

it('calculates stage progress percent', function () {
    expect(BackupStage::progressPercent('preparing'))->toBe(13);
    expect(BackupStage::progressPercent('dumping_database'))->toBe(50);
    expect(BackupStage::progressPercent('finished'))->toBe(100);
    expect(BackupStage::progressPercent(null))->toBe(0);
});

it('builds progress payload for running backup', function () {
    $history = BackupHistory::factory()->running()->create([
        'current_stage' => BackupStage::Compressing->value,
        'metadata' => ['profile_name' => 'Prod Backup'],
    ]);

    $history->logs()->create([
        'stage' => BackupStage::Preparing->value,
        'level' => 'info',
        'message' => 'Building runtime configuration.',
    ]);

    $progress = app(BackupProgressService::class)->forHistory($history);

    expect($progress['profile_name'])->toBe('Prod Backup');
    expect($progress['percent'])->toBe(63);
    expect($progress['is_running'])->toBeTrue();
    expect($progress['is_finished'])->toBeFalse();
    expect(collect($progress['stages'])->firstWhere('key', 'compressing')['state'])->toBe('current');
});

it('builds progress payload for completed backup', function () {
    $history = BackupHistory::factory()->success()->create([
        'message' => 'Backup completed successfully.',
    ]);

    $progress = app(BackupProgressService::class)->forHistory($history);

    expect($progress['is_finished'])->toBeTrue();
    expect($progress['percent'])->toBe(100);
    expect($progress['status'])->toBe(BackupHistoryStatus::Success->value);
});
