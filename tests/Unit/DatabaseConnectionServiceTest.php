<?php

use App\Models\DatabaseConnection;
use App\Repositories\DatabaseConnectionRepository;
use App\Services\Database\DatabaseConnectionService;
use App\Support\BackupLogger;
use Illuminate\Support\Facades\RateLimiter;

it('paginates connections with search filter', function () {
    DatabaseConnection::factory()->create(['name' => 'Find Me']);
    DatabaseConnection::factory()->create(['name' => 'Other']);

    $results = app(DatabaseConnectionRepository::class)->paginate(search: 'Find');

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Find Me');
});

it('stores failed connection test result', function () {
    $connection = DatabaseConnection::factory()->create([
        'host' => 'invalid.host.test',
        'port' => 3306,
    ]);

    RateLimiter::clear('database-connection-test:1');

    $result = app(DatabaseConnectionService::class)->test($connection, userId: 1);

    expect($result->success)->toBeFalse();
    expect($result->errorMessage)->not->toBeEmpty();

    $connection->refresh();
    expect($connection->last_test_status)->toBe('failed');
    expect($connection->last_test_error)->not->toBeNull();
});

it('enforces rate limiting on connection tests', function () {
    $connection = DatabaseConnection::factory()->create();
    $service = app(DatabaseConnectionService::class);

    RateLimiter::clear('database-connection-test:99');

    for ($i = 0; $i < 10; $i++) {
        $service->test($connection, userId: 99);
    }

    expect(fn () => $service->test($connection, userId: 99))
        ->toThrow(App\Exceptions\DatabaseConnectionException::class);
});

it('logs connection lifecycle events', function () {
    $logger = Mockery::mock(BackupLogger::class);
    $logger->shouldReceive('info')->once();

    $service = new DatabaseConnectionService(
        app(DatabaseConnectionRepository::class),
        $logger,
    );

    $service->create([
        'name' => 'Logged DB',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database_name' => 'test',
        'username' => 'root',
        'password' => 'secret',
        'is_active' => true,
    ]);
});
