<?php

namespace App\Providers;

use App\Events\Backup\BackupCompleted;
use App\Events\Backup\BackupFailed;
use App\Events\Backup\BackupStarted;
use App\Listeners\Backup\LogBackupLifecycle;
use App\Listeners\Backup\SendBackupNotifications;
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
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use App\Support\Backup\Dumpers\StructureAwareMySql;
use App\Support\Backup\Dumpers\WindowsCompatibleMySql;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BackupLogger::class);

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
    }
}
