<?php

use App\Models\DatabaseConnection;
use App\Models\MyDumperExport;
use App\Models\MyDumperExportProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mock(\App\Services\MyDumper\MyDumperPreflightValidator::class, function ($mock) {
        $mock->shouldReceive('validateProfile')->andReturnNull();
        $mock->shouldReceive('validateBinary')->andReturnNull();
        $mock->shouldReceive('validateStagingDirectory')->andReturn(storage_path('app/mydumper-exports/test'));
    });
});

it('renders mydumper export index page', function () {
    actingAsAdmin();

    $this->get(route('mydumper-exports.index'))
        ->assertOk()
        ->assertSee('MyDumper Export')
        ->assertSee('New Export');
});

it('renders create export form', function () {
    actingAsAdmin();

    $this->get(route('mydumper-exports.create'))
        ->assertOk()
        ->assertSee('New Export')
        ->assertSee('Command Preview');
});

it('creates export profile and dispatches job chain', function () {
    Bus::fake();
    actingAsAdmin();

    $profile = MyDumperExportProfile::factory()->create();
    $connection = $profile->databaseConnection;

    $this->post(route('mydumper-exports.store'), [
        'name' => 'Test Export Job',
        'database_connection_id' => $connection->id,
        'storage_destination_id' => $profile->storage_destination_id,
        'export_type' => 'full',
        'threads' => 4,
        'compression' => false,
        'schedule_type' => 'manual',
        'run_immediately' => true,
        'options' => ['build_metadata' => true, 'lock_mode' => 'auto'],
    ])->assertRedirect();

    expect(MyDumperExport::query()->where('name', 'Test Export Job')->exists())->toBeTrue();
    Bus::assertChained([
        \App\Jobs\MyDumper\RunMyDumperExportJob::class,
        \App\Jobs\MyDumper\UploadExportJob::class,
        \App\Jobs\MyDumper\VerifyExportJob::class,
        \App\Jobs\MyDumper\CleanupExportJob::class,
    ]);
});

it('shows export detail page', function () {
    actingAsAdmin();

    $export = MyDumperExport::factory()->success()->create();

    $this->get(route('mydumper-exports.show', $export))
        ->assertOk()
        ->assertSee($export->name);
});

it('returns progress json', function () {
    actingAsAdmin();

    $export = MyDumperExport::factory()->running()->create();

    $this->getJson(route('mydumper-exports.progress', $export))
        ->assertOk()
        ->assertJsonPath('status', 'running');
});

it('forbids access without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('mydumper-exports.index'))->assertForbidden();
});

it('validates selected tables requirement', function () {
    actingAsAdmin();

    $profile = MyDumperExportProfile::factory()->create();

    $this->from(route('mydumper-exports.create'))
        ->post(route('mydumper-exports.store'), [
            'name' => 'Invalid Export',
            'database_connection_id' => $profile->database_connection_id,
            'storage_destination_id' => $profile->storage_destination_id,
            'export_type' => 'selected_tables',
            'threads' => 4,
            'schedule_type' => 'manual',
            'selected_tables' => [],
        ])
        ->assertSessionHasErrors('selected_tables');
});

it('previews command via json endpoint', function () {
    actingAsAdmin();

    $connection = DatabaseConnection::factory()->create();

    $this->postJson(route('mydumper-exports.preview-command'), [
        'database_connection_id' => $connection->id,
        'export_type' => 'full',
        'threads' => 4,
        'compression' => true,
        'options' => ['build_metadata' => true],
    ])->assertOk()
        ->assertJsonStructure(['command']);
});
