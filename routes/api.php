<?php

use Illuminate\Http\Request;
use App\Http\Controllers\ConversionController;
use Illuminate\Support\Facades\Route;

// Webhook endpoint (simulate Bitrix → FB)
Route::post('/conversion/{token}', [ConversionController::class, 'handle']);

// Test route to simulate data without real Bitrix
Route::get('/conversion-test', function () {
    return [
        'status' => 'ok',
        'message' => 'Conversion endpoint is reachable!',
    ];
});

