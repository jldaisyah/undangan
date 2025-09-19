<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RepositoryIndexController;

Route::get('/', function () {
    return view('welcome');
});

// Repository Index Routes
Route::get('/repository', [RepositoryIndexController::class, 'index'])->name('repository.index');
Route::get('/repository/{id}', [RepositoryIndexController::class, 'show'])->name('repository.show');
Route::get('/api/repository', [RepositoryIndexController::class, 'api'])->name('repository.api');
