<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\UserController;
use App\Http\Controllers\Api\Farmer\BirthRecordController;
use App\Http\Controllers\Api\Shared\RoleController;
use App\Http\Controllers\Api\Shared\LocationController;
use App\Http\Controllers\Api\Shared\SpeciesBreedController;
use App\Http\Controllers\Api\Farmer\FarmerController;
use App\Http\Controllers\Api\Farmer\LivestockController;
use App\Http\Controllers\Api\Farmer\BreedingController; // ADDED
use App\Http\Controllers\Api\Farmer\HealthRecordController;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // ========================================
    // PUBLIC ROUTES (No Auth Required)
    // ========================================
    Route::post('/register', [UserController::class, 'store']);
    Route::post('/login', [UserController::class, 'login']);
    Route::post('/password/reset-request', [UserController::class, 'requestPasswordReset']);
    Route::post('/password/reset', [UserController::class, 'resetPassword']);

    // ========================================
    // PROTECTED ROUTES (Require auth:sanctum)
    // ========================================
    Route::middleware('auth:sanctum')->group(function () {

        // Sanctum: Get authenticated user
        Route::get('/user', fn(Request $request) => response()->json([
            'status' => 'success',
            'data' => $request->user()->load('roles')
        ]));

        // Logout
        Route::post('/logout', [UserController::class, 'logout']);

        // USER MANAGEMENT
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/{user}', [UserController::class, 'show']);
            Route::put('/{user}', [UserController::class, 'update']);
            Route::delete('/{user}', [UserController::class, 'destroy']);
        });

        // CURRENT USER PROFILE
        Route::get('/profile', [UserController::class, 'show']);
        Route::put('/profile', [UserController::class, 'update']);

        // ROLES
        Route::get('/roles', [RoleController::class, 'index']);

        // ========================================
        // FARMER MODULE
        // ========================================
        Route::prefix('farmer')->group(function () {
            Route::get('/profile', [FarmerController::class, 'profile']);
            Route::post('/register', [FarmerController::class, 'register']);
            Route::put('/profile', [FarmerController::class, 'updateProfile']);
            Route::get('/dashboard', [FarmerController::class, 'dashboard']);
        });

        // ========================================
        // LIVESTOCK MODULE
        // ========================================
        Route::prefix('livestock')->group(function () {
            Route::get('/', [LivestockController::class, 'index']);
            Route::post('/', [LivestockController::class, 'store']);
            Route::get('/summary', [LivestockController::class, 'summary']);
            Route::get('/dropdowns', [LivestockController::class, 'dropdowns']);
            Route::get('/{animal_id}', [LivestockController::class, 'show']);
            Route::put('/{animal_id}', [LivestockController::class, 'update']);
            Route::delete('/{animal_id}', [LivestockController::class, 'destroy']);
        });

        // ========================================
        // BREEDING MODULE - FIXED & WORKING!
        // ========================================
        Route::prefix('breedings')->group(function () {
            Route::get('/', [BreedingController::class, 'index']);
            Route::post('/', [BreedingController::class, 'store']);
            Route::get('/summary', [BreedingController::class, 'summary']);
            Route::get('/alerts', [BreedingController::class, 'alerts']);
            Route::get('/dropdowns', [BreedingController::class, 'dropdowns']);
            Route::get('/{breeding_id}', [BreedingController::class, 'show']);
            Route::put('/{breeding_id}', [BreedingController::class, 'update']);
            Route::delete('/{breeding_id}', [BreedingController::class, 'destroy']);
        });

        //===================================
        //Birth Records
        //=================================
        Route::prefix('births')->group(function () {
            Route::get('/', [BirthRecordController::class, 'index']);
            Route::post('/', [BirthRecordController::class, 'store']);
            Route::get('/summary', [BirthRecordController::class, 'summary']);
            Route::get('/alerts', [BirthRecordController::class, 'alerts']);
            Route::get('/dropdowns', [BirthRecordController::class, 'dropdowns']);
            Route::get('/{birth_id}', [BirthRecordController::class, 'show']);
            Route::put('/{birth_id}', [BirthRecordController::class, 'update']);
            Route::delete('/{birth_id}', [BirthRecordController::class, 'destroy']);
        });

        //=========================================
        //   HEALTHY RECORDS
        //=========================================
        Route::prefix('health')->group(function () {
                Route::get('/', [HealthRecordController::class, 'index']);
                Route::post('/', [HealthRecordController::class, 'store']);
                Route::get('/summary', [HealthRecordController::class, 'summary']);
                Route::get('/alerts', [HealthRecordController::class, 'alerts']);
                Route::get('/dropdowns', [HealthRecordController::class, 'dropdowns']);
                Route::get('/{health_id}', [HealthRecordController::class, 'show']);
                Route::put('/{health_id}', [HealthRecordController::class, 'update']);
                Route::delete('/{health_id}', [HealthRecordController::class, 'destroy']);
                Route::get('/{health_id}/pdf', [HealthRecordController::class, 'downloadPdf']);
                Route::get('/export/excel', [HealthRecordController::class, 'downloadExcel']);
                Route::get('/export/all-pdf', [HealthRecordController::class, 'downloadAllPdf']);
        });

        // ========================================
        // SHARED: SPECIES & BREED
        // ========================================
        Route::prefix('utilities')->group(function () {
            Route::prefix('species')->group(function () {
                Route::get('/', [SpeciesBreedController::class, 'speciesIndex']);
                Route::post('/', [SpeciesBreedController::class, 'speciesStore']);
                Route::get('/{id}', [SpeciesBreedController::class, 'speciesShow']);
                Route::put('/{id}', [SpeciesBreedController::class, 'speciesUpdate']);
                Route::delete('/{id}', [SpeciesBreedController::class, 'speciesDestroy']);
            });

            Route::prefix('breeds')->group(function () {
                Route::get('/', [SpeciesBreedController::class, 'breedsIndex']);
                Route::post('/', [SpeciesBreedController::class, 'breedsStore']);
                Route::get('/{id}', [SpeciesBreedController::class, 'breedsShow']);
                Route::put('/{id}', [SpeciesBreedController::class, 'breedsUpdate']);
                Route::delete('/{id}', [SpeciesBreedController::class, 'breedsDestroy']);
            });

            Route::get('/species/{species_id}/breeds', [SpeciesBreedController::class, 'breedsBySpecies']);
        });

        // ========================================
        // LOCATION MANAGEMENT
        // ========================================
        Route::prefix('locations')->group(function () {
            Route::get('/regions', [LocationController::class, 'indexRegions']);
            Route::get('/districts', [LocationController::class, 'indexDistricts']);
            Route::post('/districts', [LocationController::class, 'storeDistrict']);
            Route::get('/regions/{region_id}/districts', [LocationController::class, 'indexDistricts']);

            Route::prefix('wards')->group(function () {
                Route::get('/', [LocationController::class, 'indexWards']);
                Route::post('/', [LocationController::class, 'storeWard']);
                Route::get('/{id}', [LocationController::class, 'showWard']);
                Route::match(['put', 'patch'], '/{id}', [LocationController::class, 'updateWard']);
                Route::delete('/{id}', [LocationController::class, 'destroyWard']);
            });

            Route::get('/districts/{district_id}/wards', [LocationController::class, 'indexWards']);
            Route::get('/regions/{region_id}/wards', [LocationController::class, 'indexWards']);
            Route::get('/locations', [LocationController::class, 'indexLocations']);
            Route::post('/locations', [LocationController::class, 'storeLocation']);
            Route::get('/locations/{id}', [LocationController::class, 'showLocation']);

            Route::prefix('user-locations')->group(function () {
                Route::get('/', [LocationController::class, 'indexUserLocations']);
                Route::post('/', [LocationController::class, 'assignUserLocation']);
                Route::patch('/{id}/primary', [LocationController::class, 'setPrimaryLocation']);
                Route::delete('/{id}', [LocationController::class, 'removeUserLocation']);
            });
        });
    }); // End auth:sanctum
}); // End v1 prefix
