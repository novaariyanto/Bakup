<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BackupHistoryController;
use App\Http\Controllers\BackupHistoryDownloadController;
use App\Http\Controllers\BackupProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseConnectionController;
use App\Http\Controllers\MyDumperExportController;
use App\Http\Controllers\NotificationChannelController;
use App\Http\Controllers\StorageDestinationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('database-connections/test-form', [DatabaseConnectionController::class, 'testForm'])
        ->name('database-connections.test-form');
    Route::post('database-connections/{database_connection}/test-form', [DatabaseConnectionController::class, 'testForm'])
        ->name('database-connections.test-form.edit');
    Route::post('database-connections/{database_connection}/test', [DatabaseConnectionController::class, 'test'])
        ->name('database-connections.test');
    Route::resource('database-connections', DatabaseConnectionController::class)->except(['show']);

    Route::post('storage-destinations/test-form', [StorageDestinationController::class, 'testForm'])
        ->name('storage-destinations.test-form');
    Route::post('storage-destinations/{backup_destination}/test-form', [StorageDestinationController::class, 'testForm'])
        ->name('storage-destinations.test-form.edit');
    Route::post('storage-destinations/{backup_destination}/test', [StorageDestinationController::class, 'test'])
        ->name('storage-destinations.test');
    Route::resource('storage-destinations', StorageDestinationController::class)
        ->parameters(['storage-destinations' => 'backup_destination'])
        ->except(['show']);

    Route::post('notifications/test-form', [NotificationChannelController::class, 'testForm'])
        ->name('notifications.test-form');
    Route::post('notifications/{notification_channel}/test-form', [NotificationChannelController::class, 'testForm'])
        ->name('notifications.test-form.edit');
    Route::post('notifications/{notification_channel}/test', [NotificationChannelController::class, 'test'])
        ->name('notifications.test');
    Route::resource('notifications', NotificationChannelController::class)
        ->parameters(['notifications' => 'notification_channel'])
        ->except(['show']);

    Route::get('backup-profiles/tables/{database_connection}', [BackupProfileController::class, 'tables'])
        ->name('backup-profiles.tables');
    Route::post('backup-profiles/{backup_profile}/run', [BackupProfileController::class, 'runBackup'])
        ->name('backup-profiles.run');
    Route::get('backup-profiles/progress/{history}', [BackupProfileController::class, 'progress'])
        ->name('backup-profiles.progress');
    Route::resource('backup-profiles', BackupProfileController::class)->except(['show']);

    Route::get('mydumper-exports/tables/{database_connection}', [MyDumperExportController::class, 'tables'])
        ->name('mydumper-exports.tables');
    Route::post('mydumper-exports/preview-command', [MyDumperExportController::class, 'previewCommand'])
        ->name('mydumper-exports.preview-command');
    Route::get('mydumper-exports/progress/{export}', [MyDumperExportController::class, 'progress'])
        ->name('mydumper-exports.progress');
    Route::post('mydumper-exports/bulk', [MyDumperExportController::class, 'bulk'])
        ->name('mydumper-exports.bulk');
    Route::get('mydumper-exports/{export}/download-log', [MyDumperExportController::class, 'downloadLog'])
        ->name('mydumper-exports.download-log');
    Route::get('mydumper-exports/{export}/download-metadata', [MyDumperExportController::class, 'downloadMetadata'])
        ->name('mydumper-exports.download-metadata');
    Route::get('mydumper-exports/{export}/download-file/{fileId}', [MyDumperExportController::class, 'downloadFile'])
        ->name('mydumper-exports.download-file');
    Route::post('mydumper-exports/{export}/cancel', [MyDumperExportController::class, 'cancel'])
        ->name('mydumper-exports.cancel');
    Route::post('mydumper-exports/{export}/retry', [MyDumperExportController::class, 'retry'])
        ->name('mydumper-exports.retry');
    Route::resource('mydumper-exports', MyDumperExportController::class)
        ->parameters(['mydumper-exports' => 'export'])
        ->only(['index', 'create', 'store', 'show', 'destroy']);

    Route::get('backup-history/progress/{history}', [BackupHistoryController::class, 'progress'])
        ->name('backup-history.progress');
    Route::post('backup-history/{history}/retry', [BackupHistoryController::class, 'retry'])
        ->name('backup-history.retry');
    Route::delete('backup-history/{history}', [BackupHistoryController::class, 'destroy'])
        ->name('backup-history.destroy');
    Route::get('/backup-history', [BackupHistoryController::class, 'index'])->name('backup-history.index');
    Route::get('/backup-history/{history}/download', BackupHistoryDownloadController::class)->name('backup-history.download');
});
