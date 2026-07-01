<?php

use App\Support\BackupLogger;
use Illuminate\Support\Facades\Log;

it('writes backup logs to backup channel', function () {
    Log::shouldReceive('channel')
        ->once()
        ->with('backup')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with('Backup started', ['profile' => 'demo']);

    app(BackupLogger::class)->info('Backup started', ['profile' => 'demo']);
});

it('backup manager exception exposes user friendly message', function () {
    $exception = new class('Technical error', 'Pesan untuk pengguna') extends App\Exceptions\BackupManagerException {};

    expect($exception->userMessage())->toBe('Pesan untuk pengguna');
});
