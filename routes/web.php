<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LogsController;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::get('/login', [AuthController::class, 'showLogin'])
    ->name('login')
    ->middleware('guest');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('guest');

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');
Route::middleware('auth')->group(function () {
// Home (optional)
Route::get('/', function () {
    return redirect()->route('companies.index');
});

// Companies CRUD
Route::resource('companies', CompanyController::class)
    ->except(['show']);


Route::get('/logs', [LogsController::class, 'index'])->name('logs.index');
Route::get('/logs/{log}', [LogsController::class, 'show'])->name('logs.show');

});