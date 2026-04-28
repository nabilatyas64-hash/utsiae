<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\LaundryServiceController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/services', [LaundryServiceController::class, 'index']);
Route::get('/services/{id}', [LaundryServiceController::class, 'show']);
Route::post('/services', [LaundryServiceController::class, 'store']);
