<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\EstablishmentController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::get('/profile', [PlayerController::class, 'profile'])->middleware('role:player');
    Route::get('/fixtures', [PlayerController::class, 'fixtures'])->middleware('role:player');
    Route::get('/player/pitches', [PlayerController::class, 'pitches'])->middleware('role:player');
    Route::get('/establishment/pitches', [EstablishmentController::class, 'pitches'])->middleware('role:establishment');
    Route::get('/users', [AdminController::class, 'users'])->middleware('role:admin');
});

Route::get('/reset-password/{token}', [AuthController::class, 'validateResetToken'])->name('password.reset');
