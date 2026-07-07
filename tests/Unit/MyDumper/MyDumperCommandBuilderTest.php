<?php

use App\DTO\MyDumper\MyDumperExportOptions;
use App\Enums\MyDumper\MyDumperExportType;
use App\Models\DatabaseConnection;
use App\Services\MyDumper\MyDumperCommandBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds full export command with masked password', function () {
    $connection = DatabaseConnection::factory()->create([
        'username' => 'root',
        'password' => 'secret123',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database_name' => 'employees',
    ]);

    $builder = app(MyDumperCommandBuilder::class);
    $command = $builder->build(
        connection: $connection,
        database: 'employees',
        outputDirectory: '/tmp/export',
        exportType: MyDumperExportType::Full,
        threads: 8,
        compression: true,
        options: MyDumperExportOptions::fromArray(['trx_consistency_only' => true]),
        maskPassword: true,
    );

    expect($command)->toContain('mydumper')
        ->and($command)->toContain('-u')
        ->and($command)->toContain('root')
        ->and($command)->toContain('******')
        ->and($command)->not->toContain('secret123')
        ->and($command)->toContain('--compress')
        ->and($command)->toContain('--trx-consistency-only')
        ->and($command)->toContain('-t')
        ->and($command)->toContain('8');
});

it('adds schema only flag', function () {
    $connection = DatabaseConnection::factory()->create();

    $command = app(MyDumperCommandBuilder::class)->build(
        connection: $connection,
        database: 'test',
        outputDirectory: '/tmp/export',
        exportType: MyDumperExportType::SchemaOnly,
        threads: 4,
        compression: false,
        options: MyDumperExportOptions::fromArray([]),
    );

    expect($command)->toContain('--no-data');
});

it('adds selected tables', function () {
    $connection = DatabaseConnection::factory()->create();

    $command = app(MyDumperCommandBuilder::class)->build(
        connection: $connection,
        database: 'test',
        outputDirectory: '/tmp/export',
        exportType: MyDumperExportType::SelectedTables,
        threads: 4,
        compression: false,
        options: MyDumperExportOptions::fromArray([]),
        selectedTables: ['users', 'orders'],
    );

    expect(implode(' ', $command))->toContain('-T users')
        ->and(implode(' ', $command))->toContain('-T orders');
});

it('omits lock mode flag when auto is selected', function () {
    $connection = DatabaseConnection::factory()->create();

    $command = app(MyDumperCommandBuilder::class)->build(
        connection: $connection,
        database: 'test',
        outputDirectory: '/tmp/export',
        exportType: MyDumperExportType::Full,
        threads: 4,
        compression: false,
        options: MyDumperExportOptions::fromArray(['lock_mode' => 'auto']),
    );

    expect(implode(' ', $command))->not->toContain('--lock-mode')
        ->and(implode(' ', $command))->not->toContain('--sync-thread-lock-mode');
});

it('uses sync-thread-lock-mode for non-auto lock modes', function () {
    $this->mock(\App\Services\MyDumper\MyDumperBinaryResolver::class, function ($mock) {
        $mock->shouldReceive('version')->andReturn('0.12.0');
    });

    $connection = DatabaseConnection::factory()->create();

    $command = app(MyDumperCommandBuilder::class)->build(
        connection: $connection,
        database: 'test',
        outputDirectory: '/tmp/export',
        exportType: MyDumperExportType::Full,
        threads: 4,
        compression: false,
        options: MyDumperExportOptions::fromArray(['lock_mode' => 'no_lock']),
    );

    expect($command)->toContain('--sync-thread-lock-mode')
        ->and($command)->toContain('NO_LOCK')
        ->and($command)->not->toContain('--lock-mode');
});

it('formats preview command as multiline string', function () {
    $connection = DatabaseConnection::factory()->create();

    $preview = app(MyDumperCommandBuilder::class)->preview(
        connection: $connection,
        database: 'test',
        outputDirectory: '/tmp/export',
        exportType: MyDumperExportType::Full,
        threads: 4,
        compression: true,
        options: MyDumperExportOptions::fromArray([]),
    );

    expect($preview)->toContain('mydumper')
        ->and($preview)->toContain('******');
});
