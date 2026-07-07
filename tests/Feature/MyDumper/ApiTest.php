<?php

use App\Models\MyDumperExport;
use App\Models\MyDumperExportProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = actingAsAdmin();

    $this->mock(\App\Services\MyDumper\MyDumperPreflightValidator::class, function ($mock) {
        $mock->shouldReceive('validateProfile')->andReturnNull();
        $mock->shouldReceive('validateBinary')->andReturnNull();
        $mock->shouldReceive('validateStagingDirectory')->andReturn(storage_path('app/mydumper-exports/test'));
    });
});

it('lists exports via api', function () {
    MyDumperExport::factory()->count(2)->create();

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/exports')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('creates export via api', function () {
    Bus::fake();
    $profile = MyDumperExportProfile::factory()->create();

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/exports', [
        'name' => 'API Export',
        'database_connection_id' => $profile->database_connection_id,
        'storage_destination_id' => $profile->storage_destination_id,
        'export_type' => 'full',
        'threads' => 4,
        'schedule_type' => 'manual',
        'options' => ['build_metadata' => true],
    ])->assertCreated();

    Bus::assertChained([
        \App\Jobs\MyDumper\RunMyDumperExportJob::class,
        \App\Jobs\MyDumper\UploadExportJob::class,
        \App\Jobs\MyDumper\VerifyExportJob::class,
        \App\Jobs\MyDumper\CleanupExportJob::class,
    ]);
});

it('shows single export via api', function () {
    $export = MyDumperExport::factory()->create();
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/exports/'.$export->id)
        ->assertOk()
        ->assertJsonPath('data.id', $export->id);
});

it('retries failed export via api', function () {
    Bus::fake();
    $export = MyDumperExport::factory()->failed()->create();
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/exports/'.$export->id.'/retry')
        ->assertOk();

    Bus::assertChained([
        \App\Jobs\MyDumper\RunMyDumperExportJob::class,
        \App\Jobs\MyDumper\UploadExportJob::class,
        \App\Jobs\MyDumper\VerifyExportJob::class,
        \App\Jobs\MyDumper\CleanupExportJob::class,
    ]);
});

it('deletes finished export via api', function () {
    $export = MyDumperExport::factory()->success()->create();
    Sanctum::actingAs($this->admin);

    $this->deleteJson('/api/exports/'.$export->id)
        ->assertOk();

    expect(MyDumperExport::find($export->id))->toBeNull();
});

it('rejects api without token', function () {
    $this->refreshApplication();

    $this->getJson('/api/exports')->assertUnauthorized();
});

it('rejects api for user without permission', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/exports')->assertForbidden();
});
