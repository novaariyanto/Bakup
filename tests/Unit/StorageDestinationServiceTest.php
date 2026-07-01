<?php

use App\Enums\StorageDriver;
use App\Models\BackupDestination;
use App\Repositories\BackupDestinationRepository;
use App\Services\Storage\Drivers\LocalStorageDriver;
use App\Services\Storage\StorageDestinationService;
use App\Support\BackupLogger;
use Illuminate\Support\Facades\RateLimiter;

it('paginates destinations with search filter', function () {
    BackupDestination::factory()->create(['name' => 'Find Me Storage']);
    BackupDestination::factory()->create(['name' => 'Other Storage']);

    $results = app(BackupDestinationRepository::class)->paginate(search: 'Find');

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Find Me Storage');
});

it('tests local storage driver successfully', function () {
    $path = 'unit-test-'.uniqid();
    $driver = new LocalStorageDriver;

    $result = $driver->test(['path' => $path]);

    expect($result->success)->toBeTrue();
    expect($result->resolvedPath)->toBe(storage_path('app/backups/'.$path));
});

it('stores failed storage test result', function () {
    $destination = BackupDestination::factory()->sftp()->create([
        'config' => [
            'host' => 'invalid.sftp.host.test',
            'port' => 22,
            'username' => 'backup',
            'auth_method' => 'password',
            'password' => 'secret',
            'root' => '/',
        ],
    ]);

    RateLimiter::clear('storage-destination-test:1');

    $result = app(StorageDestinationService::class)->test($destination, userId: 1);

    expect($result->success)->toBeFalse();
    expect($result->errorMessage)->not->toBeEmpty();

    $destination->refresh();
    expect($destination->last_test_status)->toBe('failed');
    expect($destination->last_test_error)->not->toBeNull();
});

it('enforces rate limiting on storage tests', function () {
    $destination = BackupDestination::factory()->local('rate-limit-test')->create();
    $service = app(StorageDestinationService::class);

    RateLimiter::clear('storage-destination-test:99');

    for ($i = 0; $i < 10; $i++) {
        $service->test($destination, userId: 99);
    }

    expect(fn () => $service->test($destination, userId: 99))
        ->toThrow(App\Exceptions\StorageDestinationException::class);
});

it('preserves secrets when updating s3 destination', function () {
    $destination = BackupDestination::factory()->s3()->create();

    $updated = app(StorageDestinationService::class)->update($destination, [
        'name' => 'Updated Bucket',
        'config' => [
            'key' => 'new-key',
            'secret' => '',
            'region' => 'ap-southeast-1',
            'bucket' => 'new-bucket',
        ],
    ]);

    expect($updated->config['secret'])->toBe('test-secret');
    expect($updated->config['key'])->toBe('new-key');
    expect($updated->driver)->toBe(StorageDriver::S3);
});

it('logs storage destination lifecycle events', function () {
    $logger = Mockery::mock(BackupLogger::class);
    $logger->shouldReceive('info')->once();

    $service = new StorageDestinationService(
        app(BackupDestinationRepository::class),
        app(App\Services\Storage\StorageDriverManager::class),
        $logger,
    );

    $service->create([
        'name' => 'Logged Storage',
        'driver' => StorageDriver::Local,
        'config' => ['path' => 'logged-test'],
        'is_active' => true,
    ]);
});
