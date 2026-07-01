<?php

use App\Jobs\Backup\ExecuteBackupJob;
use App\Models\BackupProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

it('processes due backup profiles via artisan command', function () {
    Carbon::setTestNow('2026-07-01 03:00:00');
    Bus::fake();

    BackupProfile::factory()->daily('03:00')->dueNow()->create();
    BackupProfile::factory()->create([
        'schedule_type' => 'manual',
        'next_run_at' => null,
    ]);

    Artisan::call('backup:process');

    expect(Artisan::output())->toContain('Processed 1 backup profile(s).');
    Bus::assertDispatched(ExecuteBackupJob::class);
});

it('skips profiles that are not yet due', function () {
    Carbon::setTestNow('2026-07-01 02:00:00');
    Bus::fake();

    BackupProfile::factory()->daily('03:00')->create([
        'next_run_at' => now()->addHour(),
    ]);

    Artisan::call('backup:process');

    expect(Artisan::output())->toContain('Processed 0 backup profile(s).');
    Bus::assertNothingDispatched();
});

it('registers backup process command in scheduler', function () {
    $events = Illuminate\Support\Facades\Schedule::events();

    expect(collect($events)->contains(fn ($event) => str_contains($event->command ?? '', 'backup:process')))->toBeTrue();
});
