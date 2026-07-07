<?php

use App\Enums\MyDumper\MyDumperExportStatus;
use App\Enums\MyDumper\MyDumperExportType;
use App\Models\MyDumperExport;
use App\Services\MyDumper\MyDumperVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('verifies export folder and indexes files', function () {
    $export = MyDumperExport::factory()->create([
        'status' => MyDumperExportStatus::Running,
        'type' => MyDumperExportType::Full,
        'options_snapshot' => ['build_metadata' => true],
    ]);

    $outputPath = storage_path('app/mydumper-test/'.$export->uuid);
    File::ensureDirectoryExists($outputPath);
    File::put($outputPath.'/metadata', '{"tables":[]}');
    File::put($outputPath.'/users-schema.sql', 'CREATE TABLE users');

    $export->update(['output_path' => $outputPath]);

    app(MyDumperVerificationService::class)->verify($export->fresh());

    $export->refresh();

    expect($export->verification_status)->toBe('passed')
        ->and($export->file_count)->toBe(2)
        ->and($export->files)->toHaveCount(2);

    File::deleteDirectory($outputPath);
});

it('fails verification when folder is empty', function () {
    $export = MyDumperExport::factory()->create();
    $outputPath = storage_path('app/mydumper-test-empty/'.$export->uuid);
    File::ensureDirectoryExists($outputPath);
    $export->update(['output_path' => $outputPath]);

    expect(fn () => app(MyDumperVerificationService::class)->verify($export->fresh()))
        ->toThrow(\App\Exceptions\MyDumper\MyDumperException::class);

    File::deleteDirectory($outputPath);
});
