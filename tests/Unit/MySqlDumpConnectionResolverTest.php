<?php

use App\Services\Backup\MySqlDumpConnectionResolver;

it('normalizes localhost host to 127.0.0.1', function () {
    $resolver = app(MySqlDumpConnectionResolver::class);

    expect($resolver->resolveDumpHost('localhost'))->toBe('127.0.0.1');
    expect($resolver->resolveDumpHost('::1'))->toBe('127.0.0.1');
    expect($resolver->resolveDumpHost('db.example.com'))->toBe('db.example.com');
});

it('uses configured mysql socket for local host on non-windows', function () {
    config([
        'backup.mysql_dump_socket' => '/tmp/mysql.sock',
        'backup.mysql_dump_socket_auto_detect' => false,
    ]);

    if (PHP_OS_FAMILY === 'Windows') {
        expect(true)->toBeTrue();

        return;
    }

    $resolver = app(MySqlDumpConnectionResolver::class);

    expect($resolver->resolveSocket('127.0.0.1'))->toBe('/tmp/mysql.sock');
    expect($resolver->resolveSocket('db.example.com'))->toBeNull();
});

it('auto detects laragon socket from my.ini for local host on windows', function () {
    config([
        'backup.mysql_dump_binary_path' => 'C:/laragon/bin/mysql/mysql-5.7.24-winx64/bin',
        'backup.mysql_dump_auto_detect' => false,
        'backup.mysql_dump_socket' => null,
        'backup.mysql_dump_socket_auto_detect' => true,
    ]);

    if (PHP_OS_FAMILY !== 'Windows') {
        expect(true)->toBeTrue();

        return;
    }

    $resolver = app(MySqlDumpConnectionResolver::class);

    if (! is_file('C:/laragon/bin/mysql/mysql-5.7.24-winx64/my.ini')) {
        expect(true)->toBeTrue();

        return;
    }

    expect($resolver->resolveSocket('127.0.0.1'))->toBe('/tmp/mysql.sock');
});

it('includes unix socket in runtime backup config for local windows mysql', function () {
    config([
        'backup.mysql_dump_binary_path' => 'C:/laragon/bin/mysql/mysql-5.7.24-winx64/bin',
        'backup.mysql_dump_auto_detect' => false,
        'backup.mysql_dump_socket' => null,
        'backup.mysql_dump_socket_auto_detect' => true,
    ]);

    if (PHP_OS_FAMILY !== 'Windows' || ! is_file('C:/laragon/bin/mysql/mysql-5.7.24-winx64/my.ini')) {
        expect(true)->toBeTrue();

        return;
    }

    $profile = App\Models\BackupProfile::factory()->create();
    $profile->load(['databaseConnection', 'destinations', 'excludedTables', 'includeFolders', 'excludeFolders']);

    $runtimeConfig = app(App\Services\Backup\BackupRuntimeConfigService::class)->build($profile);

    expect($runtimeConfig->databaseConnectionConfig['host'])->toBe('127.0.0.1');
    expect($runtimeConfig->databaseConnectionConfig['unix_socket'] ?? null)->toBe('/tmp/mysql.sock');
});
