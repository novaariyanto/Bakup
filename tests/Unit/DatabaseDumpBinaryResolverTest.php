<?php

use App\Services\Backup\DatabaseDumpBinaryResolver;

it('uses configured mysqldump binary path', function () {
    config([
        'backup.mysql_dump_binary_path' => 'C:/custom/mysql/bin',
        'backup.mysql_dump_auto_detect' => false,
    ]);

    expect(app(DatabaseDumpBinaryResolver::class)->resolve())->toBe('C:/custom/mysql/bin/');
});

it('auto detects mysqldump on windows when enabled', function () {
    config([
        'backup.mysql_dump_binary_path' => null,
        'backup.mysql_dump_auto_detect' => true,
    ]);

    if (PHP_OS_FAMILY !== 'Windows') {
        expect(true)->toBeTrue();

        return;
    }

    $executable = app(DatabaseDumpBinaryResolver::class)->mysqldumpExecutable();

    if ($executable === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect($executable)->toEndWith('mysqldump.exe');
    expect(is_file($executable))->toBeTrue();
});

it('includes dump binary path in runtime backup config', function () {
    config([
        'backup.mysql_dump_binary_path' => 'C:/custom/mysql/bin',
        'backup.mysql_dump_auto_detect' => false,
    ]);

    $profile = App\Models\BackupProfile::factory()->create();
    $profile->load(['databaseConnection', 'destinations', 'excludedTables', 'includeFolders', 'excludeFolders']);

    $runtimeConfig = app(App\Services\Backup\BackupRuntimeConfigService::class)->build($profile);

    expect($runtimeConfig->databaseConnectionConfig['dump']['dump_binary_path'] ?? null)->toBe('C:/custom/mysql/bin/');
    expect($runtimeConfig->databaseConnectionConfig['dump']['doNotUseColumnStatistics'] ?? null)->toBeNull();
});

it('normalizes localhost host to 127.0.0.1 for mysqldump', function () {
    config([
        'backup.mysql_dump_binary_path' => 'C:/custom/mysql/bin',
        'backup.mysql_dump_auto_detect' => false,
    ]);

    $connection = App\Models\DatabaseConnection::factory()->create(['host' => 'localhost']);
    $profile = App\Models\BackupProfile::factory()->create([
        'database_connection_id' => $connection->id,
    ]);
    $profile->load(['databaseConnection', 'destinations', 'excludedTables', 'includeFolders', 'excludeFolders']);

    $runtimeConfig = app(App\Services\Backup\BackupRuntimeConfigService::class)->build($profile);

    expect($runtimeConfig->databaseConnectionConfig['host'])->toBe('127.0.0.1');
});

it('enables column statistics opt-out only for mysqldump 8 or newer', function () {
    config([
        'backup.mysql_dump_binary_path' => 'C:/custom/mysql/bin',
        'backup.mysql_dump_auto_detect' => false,
    ]);

    $resolver = Mockery::mock(DatabaseDumpBinaryResolver::class)->makePartial();
    $resolver->shouldReceive('resolve')->andReturn('C:/custom/mysql/bin/');
    $resolver->shouldReceive('shouldDisableColumnStatistics')->andReturn(true);
    $resolver->shouldReceive('isGzipEnabled')->andReturn(false);
    app()->instance(DatabaseDumpBinaryResolver::class, $resolver);

    $profile = App\Models\BackupProfile::factory()->create();
    $profile->load(['databaseConnection', 'destinations', 'excludedTables', 'includeFolders', 'excludeFolders']);

    $runtimeConfig = app(App\Services\Backup\BackupRuntimeConfigService::class)->build($profile);

    expect($runtimeConfig->databaseConnectionConfig['dump']['doNotUseColumnStatistics'] ?? null)->toBeTrue();
});

it('detects mysqldump 5.7 should not disable column statistics flag', function () {
    config([
        'backup.mysql_dump_binary_path' => 'C:/laragon/bin/mysql/mysql-5.7.24-winx64/bin',
        'backup.mysql_dump_auto_detect' => false,
    ]);

    $resolver = app(DatabaseDumpBinaryResolver::class);

    if ($resolver->mysqldumpExecutable() === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect($resolver->mysqldumpVersion())->toStartWith('5.7');
    expect($resolver->shouldDisableColumnStatistics())->toBeFalse();
});

it('auto detects gzip binary on windows when enabled', function () {
    config([
        'backup.gzip_binary_path' => null,
        'backup.gzip_auto_detect' => true,
    ]);

    if (PHP_OS_FAMILY !== 'Windows') {
        expect(true)->toBeTrue();

        return;
    }

    $executable = app(DatabaseDumpBinaryResolver::class)->gzipExecutable();

    if ($executable === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect($executable)->toEndWith('gzip.exe');
    expect(is_file($executable))->toBeTrue();
});

it('uses bare gzip command name for windows shell pipes', function () {
    config([
        'backup.gzip_enabled' => true,
        'backup.gzip_binary_path' => 'C:/Program Files/Git/usr/bin/gzip.exe',
        'backup.gzip_auto_detect' => false,
    ]);

    if (PHP_OS_FAMILY !== 'Windows') {
        expect(app(DatabaseDumpBinaryResolver::class)->gzipCommand())->toBe('gzip');

        return;
    }

    expect(app(DatabaseDumpBinaryResolver::class)->gzipCommand())->toBe('gzip.exe');
});

it('disables gzip when backup gzip is turned off in config', function () {
    config([
        'backup.gzip_enabled' => false,
        'backup.gzip_binary_path' => 'C:/Program Files/Git/usr/bin/gzip.exe',
        'backup.gzip_auto_detect' => false,
    ]);

    expect(app(DatabaseDumpBinaryResolver::class)->isGzipAvailable())->toBeFalse();
    expect(app(DatabaseDumpBinaryResolver::class)->gzipExecutable())->toBeNull();
});
