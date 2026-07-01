<?php

use App\Console\Commands\CleanupRetentionCommand;
use App\Console\Commands\ProcessBackupsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ProcessBackupsCommand::class)->everyMinute();
Schedule::command(CleanupRetentionCommand::class)->dailyAt('04:00');
