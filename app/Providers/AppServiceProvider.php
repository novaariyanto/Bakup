<?php

namespace App\Providers;

use App\Events\Backup\BackupCompleted;
use App\Events\Backup\BackupFailed;
use App\Events\Backup\BackupStarted;
use App\Events\MyDumper\ExportCancelled;
use App\Events\MyDumper\ExportCompleted;
use App\Events\MyDumper\ExportFailed;
use App\Events\MyDumper\ExportStarted;
use App\Events\MyDumper\ExportUploadCompleted;
use App\Events\MyDumper\ExportVerificationFailed;
use App\Listeners\Backup\LogBackupLifecycle;
use App\Listeners\Backup\SendBackupNotifications;
use App\Listeners\MyDumper\LogMyDumperLifecycle;
use App\Listeners\MyDumper\RecordMyDumperActivity;
use App\Listeners\MyDumper\SendMyDumperNotifications;
use App\Services\Notification\Drivers\EmailNotificationDriver;
use App\Services\Notification\Drivers\WhatsAppNotificationDriver;
use App\Services\Notification\NotificationDriverManager;
use App\Services\Storage\Drivers\LocalStorageDriver;
use App\Services\Storage\Drivers\S3StorageDriver;
use App\Services\Storage\Drivers\SftpStorageDriver;
use App\Services\Storage\Sftp\SftpAuthenticationResolver;
use App\Services\Storage\Sftp\SftpConfigurationValidator;
use App\Services\Storage\Sftp\SftpConnectionService;
use App\Services\Storage\Sftp\SftpErrorMapper;
use App\Services\Storage\Sftp\SftpTestConnectionAction;
use App\Services\Storage\StorageDriverManager;
use App\Support\BackupLogger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use App\Models\MyDumperExport;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use App\Support\Backup\Dumpers\StructureAwareMySql;
use App\Support\Backup\Dumpers\WindowsCompatibleMySql;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BackupLogger::class);
        $this->app->singleton(\App\Support\MyDumperLogger::class);

        $this->app->singleton(SftpAuthenticationResolver::class);
        $this->app->singleton(SftpConfigurationValidator::class);
        $this->app->singleton(SftpConnectionService::class);
        $this->app->singleton(SftpErrorMapper::class);
        $this->app->singleton(SftpTestConnectionAction::class);

        $this->app->singleton(StorageDriverManager::class, function ($app): StorageDriverManager {
            return new StorageDriverManager(
                new LocalStorageDriver,
                new SftpStorageDriver(
                    $app->make(SftpConfigurationValidator::class),
                    $app->make(SftpAuthenticationResolver::class),
                    $app->make(SftpTestConnectionAction::class),
                ),
                new S3StorageDriver,
            );
        });

        $this->app->singleton(NotificationDriverManager::class, function (): NotificationDriverManager {
            return new NotificationDriverManager(
                new EmailNotificationDriver,
                new WhatsAppNotificationDriver,
            );
        });
    }

    public function boot(): void
    {
        Route::bind('export', function (string $value): MyDumperExport {
            return MyDumperExport::query()
                ->where('id', $value)
                ->orWhere('uuid', $value)
                ->firstOrFail();
        });

        if (PHP_OS_FAMILY === 'Windows') {
            DbDumperFactory::extend('mysql', fn () => WindowsCompatibleMySql::create());
        } else {
            DbDumperFactory::extend('mysql', fn () => StructureAwareMySql::create());
        }

        $logListener = LogBackupLifecycle::class;
        $notificationListener = SendBackupNotifications::class;

        Event::listen(BackupStarted::class, [$logListener, 'handleStarted']);
        Event::listen(BackupCompleted::class, [$logListener, 'handleCompleted']);
        Event::listen(BackupFailed::class, [$logListener, 'handleFailed']);
        Event::listen(BackupCompleted::class, [$notificationListener, 'handleCompleted']);
        Event::listen(BackupFailed::class, [$notificationListener, 'handleFailed']);

        $mydumperLogListener = LogMyDumperLifecycle::class;
        $mydumperNotificationListener = SendMyDumperNotifications::class;
        $mydumperActivityListener = RecordMyDumperActivity::class;

        Event::listen(ExportStarted::class, [$mydumperLogListener, 'handleStarted']);
        Event::listen(ExportStarted::class, [$mydumperActivityListener, 'handleStarted']);
        Event::listen(ExportCompleted::class, [$mydumperLogListener, 'handleCompleted']);
        Event::listen(ExportCompleted::class, [$mydumperNotificationListener, 'handleCompleted']);
        Event::listen(ExportCompleted::class, [$mydumperActivityListener, 'handleCompleted']);
        Event::listen(ExportFailed::class, [$mydumperLogListener, 'handleFailed']);
        Event::listen(ExportFailed::class, [$mydumperNotificationListener, 'handleFailed']);
        Event::listen(ExportFailed::class, [$mydumperActivityListener, 'handleFailed']);
        Event::listen(ExportCancelled::class, [$mydumperLogListener, 'handleCancelled']);
        Event::listen(ExportCancelled::class, [$mydumperNotificationListener, 'handleCancelled']);
        Event::listen(ExportCancelled::class, [$mydumperActivityListener, 'handleCancelled']);
        Event::listen(ExportUploadCompleted::class, [$mydumperNotificationListener, 'handleUploadCompleted']);
        Event::listen(ExportVerificationFailed::class, [$mydumperNotificationListener, 'handleVerificationFailed']);
    }
}
