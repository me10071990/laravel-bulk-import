<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ChunkedUploadController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Product Import Routes
Route::prefix('products')->group(function () {
    Route::post('/import', [ProductImportController::class, 'import']);
    Route::post('/link-image', [ProductImportController::class, 'linkImage']);
    Route::get('/list', [ProductImportController::class, 'products']);
});

// Chunked Upload Routes
Route::prefix('uploads')->group(function () {
    Route::post('/initialize', [ChunkedUploadController::class, 'initialize']);
    Route::post('/chunk', [ChunkedUploadController::class, 'uploadChunk']);
    Route::post('/complete', [ChunkedUploadController::class, 'complete']);
    Route::get('/status', [ChunkedUploadController::class, 'status']);
});