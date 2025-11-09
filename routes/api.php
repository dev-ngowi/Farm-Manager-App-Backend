<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\UserController;
use App\Http\Controllers\Api\Shared\RoleController;
use App\Http\Controllers\Api\Shared\LocationController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded within the "api" middleware group.
|
*/

// Sanctum: Get authenticated user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json([
        'status' => 'success',
        'data' => $request->user()->load('roles'),
    ]);
});

// API Version 1
Route::prefix('v1')->group(function () {

    // Public Routes (No Auth Required)
    Route::post('/register', [UserController::class, 'store']);
    Route::post('/login', [UserController::class, 'login']);
    Route::post('/password/reset-request', [UserController::class, 'requestPasswordReset']);
    Route::post('/password/reset', [UserController::class, 'resetPassword']);

    // Protected Routes (Require Authentication)
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [UserController::class, 'logout']);

        // User Management (Admin-only? Add role middleware if needed)
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        // Optional: Allow users to update their own profile
        Route::put('/profile', [UserController::class, 'update']); // Reuse update method with auth user
        Route::get('/profile', function (Request $request) {
            return $request->user()->load('roles');
        });

        //roles
        Route::get('/roles', [RoleController::class, 'index']);

        // WARDS CRUD - CLEAR NAMING
    Route::prefix('wards')->group(function () {
        Route::get('/', [LocationController::class, 'indexWards']);
        Route::post('/', [LocationController::class, 'storeWard']);
        Route::get('/{id}', [LocationController::class, 'showWard']);
        Route::put('/{id}', [LocationController::class, 'updateWard']);
        Route::patch('/{id}', [LocationController::class, 'updateWard']);
        Route::delete('/{id}', [LocationController::class, 'destroyWard']);
    });
    // Filter wards by parent
    Route::get('/districts/{district_id}/wards', [LocationController::class, 'indexWards']);
    Route::get('/regions/{region_id}/wards', [LocationController::class, 'indexWards']);

    // REGIONS
    Route::get('/regions', [LocationController::class, 'indexRegions']);

    // DISTRICTS
    Route::get('/districts', [LocationController::class, 'indexDistricts']);
    Route::post('/districts', [LocationController::class, 'storeDistrict']);
    Route::get('/regions/{region_id}/districts', [LocationController::class, 'indexDistricts']);


    // LOCATION CRUD
    Route::prefix('locations')->group(function () {
        Route::get('/', [LocationController::class, 'indexLocations']);
        Route::post('/', [LocationController::class, 'storeLocation']);
        Route::get('/{id}', [LocationController::class, 'showLocation']);
    });

    // USER LOCATION ASSIGNMENT
    Route::prefix('user-locations')->group(function () {
        Route::get('/', [LocationController::class, 'indexUserLocations']);
        Route::post('/', [LocationController::class, 'assignUserLocation']);
        Route::patch('/{id}/primary', [LocationController::class, 'setPrimaryLocation']);
        Route::delete('/{id}', [LocationController::class, 'removeUserLocation']);
    });

    });
});