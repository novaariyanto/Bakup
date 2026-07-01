<?php

use App\Support\Backup\Dumpers\PhpMysqlDumper;

it('creates a sql dump file using php mysql fallback', function () {
    if (config('database.default') !== 'mysql') {
        expect(true)->toBeTrue();

        return;
    }

    $connection = config('database.connections.mysql');
    $dumpFile = storage_path('app/php-dump-test.sql');

    @unlink($dumpFile);

    (new PhpMysqlDumper)->dumpToFile(
        host: $connection['host'],
        port: (int) $connection['port'],
        database: $connection['database'],
        username: $connection['username'],
        password: $connection['password'] ?? '',
        dumpFile: $dumpFile,
    );

    expect(is_file($dumpFile))->toBeTrue();
    expect(filesize($dumpFile))->toBeGreaterThan(0);
    expect(file_get_contents($dumpFile))->toContain('CREATE TABLE');

    @unlink($dumpFile);
})->group('mysql');
