<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductImportController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/import', [ProductImportController::class, 'index'])->name('import.index');

