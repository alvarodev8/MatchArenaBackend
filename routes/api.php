<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\EstablishmentController;
use App\Http\Controllers\ReservationController;

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

    // Rutas para jugadores
    Route::prefix('player')->middleware('role:player')->group(function () {
        Route::get('/profile', [PlayerController::class, 'profile']);
        Route::get('/reservations', [PlayerController::class, 'reservations']);
        Route::post('/reservations', [PlayerController::class, 'createReservation']);
        Route::get('/pitches', [PlayerController::class, 'getPitches']);
        Route::post('/reservations/check', [ReservationController::class, 'checkAvailability']);
        Route::get('/reservations/available-times', [ReservationController::class, 'getAvailableTimes']);
        Route::get('/reservations/available-dates', [ReservationController::class, 'getAvailableDates']);
        Route::post('/reservations/create-payment-intent', [ReservationController::class, 'createPaymentIntent']);
        Route::post('/reservations/{id}/modify', [ReservationController::class, 'modifyReservation']);
        Route::delete('/reservations/{id}', [ReservationController::class, 'cancelReservation']);
    });

    // Rutas para establecimientos
    Route::prefix('establishment')->middleware('role:establishment')->group(function () {
        Route::get('/pitches', [EstablishmentController::class, 'getPitches']);
        Route::get('/pitches/{id}', [EstablishmentController::class, 'showPitch']);
        Route::post('/pitches', [EstablishmentController::class, 'createPitch']);
        Route::put('/pitches/{id}', [EstablishmentController::class, 'updatePitch']);
        Route::delete('/pitches/{id}', [EstablishmentController::class, 'deletePitch']);
        Route::get('/reservations', [EstablishmentController::class, 'getReservations']);
        Route::delete('/reservations/{id}', [EstablishmentController::class, 'cancelReservation']);
    });

    // Rutas para administradores
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::get('/users/{id}', [AdminController::class, 'showUser']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
        Route::get('/users/{id}/reservations', [AdminController::class, 'getUserReservations']);
        Route::delete('/users/{userId}/reservations/{reservationId}', [AdminController::class, 'cancelReservation']);
    });
});

Route::get('/reset-password/{token}', [AuthController::class, 'validateResetToken'])->name('password.reset');
