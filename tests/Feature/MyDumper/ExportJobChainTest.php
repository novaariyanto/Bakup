<?php

use App\Enums\MyDumper\MyDumperExportStatus;
use App\Models\MyDumperExport;
use App\Services\MyDumper\MyDumperBinaryResolver;
use App\Services\MyDumper\MyDumperExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('dispatches full job chain on export dispatch', function () {
    Bus::fake();

    $export = MyDumperExport::factory()->create([
        'status' => MyDumperExportStatus::Waiting,
    ]);

    app(MyDumperExecutionService::class)->dispatchChain($export);

    Bus::assertChained([
        \App\Jobs\MyDumper\RunMyDumperExportJob::class,
        \App\Jobs\MyDumper\UploadExportJob::class,
        \App\Jobs\MyDumper\VerifyExportJob::class,
        \App\Jobs\MyDumper\CleanupExportJob::class,
    ]);
});

it('fails preflight when mydumper is not installed', function () {
    $this->mock(MyDumperBinaryResolver::class, function ($mock) {
        $mock->shouldReceive('resolve')->andReturn(null);
    });

    $export = MyDumperExport::factory()->create();

    expect(fn () => app(MyDumperExecutionService::class)->runExport($export))
        ->toThrow(\App\Exceptions\MyDumper\MyDumperException::class);
});
