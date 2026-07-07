<?php

use App\Http\Controllers\Api\MyDumperExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('exports', [MyDumperExportController::class, 'index']);
    Route::post('exports', [MyDumperExportController::class, 'store']);
    Route::get('exports/{export}', [MyDumperExportController::class, 'show']);
    Route::post('exports/{export}/run', [MyDumperExportController::class, 'run']);
    Route::post('exports/{export}/cancel', [MyDumperExportController::class, 'cancel']);
    Route::post('exports/{export}/retry', [MyDumperExportController::class, 'retry']);
    Route::delete('exports/{export}', [MyDumperExportController::class, 'destroy']);
    Route::get('exports/{export}/logs', [MyDumperExportController::class, 'logs']);
    Route::get('exports/{export}/download', [MyDumperExportController::class, 'download']);
    Route::get('exports/{export}/progress', [MyDumperExportController::class, 'progress']);
});
