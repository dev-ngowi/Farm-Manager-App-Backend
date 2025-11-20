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
use App\Http\Controllers\Api\Farmer\ExpenseController;
use App\Http\Controllers\Api\Farmer\FeedIntakeController;
use App\Http\Controllers\Api\Farmer\HealthRecordController;
use App\Http\Controllers\Api\Farmer\MilkYieldController;
use App\Http\Controllers\Api\Farmer\OffspringController;
use App\Http\Controllers\Api\Farmer\PregnancyCheckController;
use App\Http\Controllers\Api\Farmer\ProductionFactorController;
use App\Http\Controllers\Api\Farmer\ProfitLossController;
use App\Http\Controllers\Api\Farmer\SaleController;
use App\Http\Controllers\Api\Farmer\VaccinationController;
use App\Http\Controllers\Api\Farmer\WeightRecordController;
use App\Http\Controllers\Api\Vet\AppointmentController;
use App\Http\Controllers\Api\Vet\DiagnosisController;
use App\Http\Controllers\Api\Vet\VetActionController;
use App\Http\Controllers\Api\Vet\VeterinarianController;
use App\Http\Controllers\Api\Vet\VetServiceAreaController;


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

        Route::post('/assign-role', [UserController::class, 'assignRole']);

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

        //===================================
        // Birth Records - UPDATED
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

            // ADD THIS MISSING ROUTE
            Route::get('/{birth_id}/pdf', [BirthRecordController::class, 'downloadPdf']);
        });

        // ========================================
        // FEED INTAKE MODULE
        // ========================================
        Route::prefix('feed-intake')->group(function () {
            Route::get('/', [FeedIntakeController::class, 'index']);
            Route::post('/', [FeedIntakeController::class, 'store']);
            Route::get('/summary', [FeedIntakeController::class, 'summary']);
            Route::get('/alerts', [FeedIntakeController::class, 'alerts']);
            Route::get('/dropdowns', [FeedIntakeController::class, 'dropdowns']);
            Route::get('/{intake_id}', [FeedIntakeController::class, 'show']);
            Route::put('/{intake_id}', [FeedIntakeController::class, 'update']);
            Route::delete('/{intake_id}', [FeedIntakeController::class, 'destroy']);
            Route::get('/{intake_id}/pdf', [FeedIntakeController::class, 'downloadPdf']);
        });

        // ========================================
        // MILK YIELD MODULE
        // ========================================
        Route::prefix('milk-yields')->group(function () {
            Route::get('/', [MilkYieldController::class, 'index']);
            Route::post('/', [MilkYieldController::class, 'store']);
            Route::get('/summary', [MilkYieldController::class, 'summary']);
            Route::get('/alerts', [MilkYieldController::class, 'alerts']);
            Route::get('/dropdowns', [MilkYieldController::class, 'dropdowns']);
            Route::get('/{yield_id}', [MilkYieldController::class, 'show']);
            Route::put('/{yield_id}', [MilkYieldController::class, 'update']);
            Route::delete('/{yield_id}', [MilkYieldController::class, 'destroy']);
            Route::get('/{yield_id}/pdf', [MilkYieldController::class, 'downloadPdf']);
        });

        // ========================================
        // OFFSPRING MODULE
        // ========================================
        Route::prefix('offspring')->group(function () {
            Route::get('/', [OffspringController::class, 'index']);
            Route::post('/', [OffspringController::class, 'store']); // Register as livestock
            Route::get('/summary', [OffspringController::class, 'summary']);
            Route::get('/alerts', [OffspringController::class, 'alerts']);
            Route::get('/dropdowns', [OffspringController::class, 'dropdowns']);
            Route::get('/{offspring_id}', [OffspringController::class, 'show']);
            Route::put('/{offspring_id}', [OffspringController::class, 'update']);
            Route::delete('/{offspring_id}', [OffspringController::class, 'destroy']);
            Route::get('/{offspring_id}/pdf', [OffspringController::class, 'downloadPdf']);
        });

        // ========================================
        // PRODUCTION FACTOR MODULE
        // ========================================
        Route::prefix('production-factors')->group(function () {
            Route::get('/', [ProductionFactorController::class, 'index']);
            Route::post('/', [ProductionFactorController::class, 'store']);
            Route::get('/summary', [ProductionFactorController::class, 'summary']);
            Route::get('/alerts', [ProductionFactorController::class, 'alerts']);
            Route::get('/dropdowns', [ProductionFactorController::class, 'dropdowns']);
            Route::get('/{factor_id}', [ProductionFactorController::class, 'show']);
            Route::put('/{factor_id}', [ProductionFactorController::class, 'update']);
            Route::delete('/{factor_id}', [ProductionFactorController::class, 'destroy']);
            Route::get('/{factor_id}/pdf', [ProductionFactorController::class, 'downloadPdf']);
        });

        // ========================================
        // WEIGHT RECORD MODULE
        // ========================================
        Route::prefix('weight-records')->group(function () {
            Route::get('/', [WeightRecordController::class, 'index']);
            Route::post('/', [WeightRecordController::class, 'store']);
            Route::get('/summary', [WeightRecordController::class, 'summary']);
            Route::get('/alerts', [WeightRecordController::class, 'alerts']);
            Route::get('/dropdowns', [WeightRecordController::class, 'dropdowns']);
            Route::get('/{weight_id}', [WeightRecordController::class, 'show']);
            Route::put('/{weight_id}', [WeightRecordController::class, 'update']);
            Route::delete('/{weight_id}', [WeightRecordController::class, 'destroy']);
            Route::get('/{weight_id}/pdf', [WeightRecordController::class, 'downloadPdf']);
        });

        Route::prefix('pregnancy-checks')->group(function () {
            Route::get('/', [PregnancyCheckController::class, 'index']);
            Route::post('/', [PregnancyCheckController::class, 'store']);
            Route::get('/dropdowns', [PregnancyCheckController::class, 'dropdowns']);
            Route::get('/{check_id}', [PregnancyCheckController::class, 'show']);
            Route::put('/{check_id}', [PregnancyCheckController::class, 'update']);
            Route::delete('/{check_id}', [PregnancyCheckController::class, 'destroy']);
            Route::get('/{check_id}/pdf', [PregnancyCheckController::class, 'downloadPdf']);
        });


        Route::prefix('expenses')->group(function () {
            Route::get('/', [ExpenseController::class, 'index']);
            Route::post('/', [ExpenseController::class, 'store']);
            Route::get('/summary', [ExpenseController::class, 'summary']);
            Route::get('/alerts', [ExpenseController::class, 'alerts']);
            Route::get('/dropdowns', [ExpenseController::class, 'dropdowns']);
            Route::get('/{expense_id}', [ExpenseController::class, 'show']);
            Route::put('/{expense_id}', [ExpenseController::class, 'update']);
            Route::delete('/{expense_id}', [ExpenseController::class, 'destroy']);
            Route::get('/{expense_id}/pdf', [ExpenseController::class, 'downloadPdf']);
            Route::get('/category/{category_id}/report-pdf', [ExpenseController::class, 'categoryReportPdf']);
        });

        Route::prefix('profit-loss')->group(function () {
            Route::get('/report', [ProfitLossController::class, 'report']);
            Route::get('/chart', [ProfitLossController::class, 'chartData']);
            Route::get('/summary', [ProfitLossController::class, 'summary']);
            Route::get('/pdf', [ProfitLossController::class, 'downloadPdf']);
        });

        Route::prefix('sales')->group(function () {
            Route::get('/', [SaleController::class, 'index']);
            Route::post('/', [SaleController::class, 'store']);
            Route::get('/dropdowns', [SaleController::class, 'dropdowns']);
            Route::get('/{sale_id}/pdf', [SaleController::class, 'downloadPdf']);
        });

        Route::prefix('vets')->group(function () {
            Route::get('/', [VeterinarianController::class, 'index']);
            Route::get('/{vet_id}', [VeterinarianController::class, 'show']);
            Route::post('/register', [VeterinarianController::class, 'register']);
            Route::post('/find', [VetServiceAreaController::class, 'findByLocation']);
        });

        Route::put('/vet/profile', [VeterinarianController::class, 'updateProfile']);

        Route::prefix('vet/{vet_id}/areas')->group(function () {
            Route::get('/', [VetServiceAreaController::class, 'index']);
            Route::post('/', [VetServiceAreaController::class, 'store']);
            Route::put('/{area_id}', [VetServiceAreaController::class, 'update']);
            Route::delete('/{area_id}', [VetServiceAreaController::class, 'destroy']);
        });

        Route::get('/vets/pending', [VeterinarianController::class, 'pending']);
        Route::post('/vets/{vet_id}/approve', [VeterinarianController::class, 'approve']);
        Route::post('/vets/{vet_id}/reject', [VeterinarianController::class, 'reject']);

        Route::prefix('diagnosis')->middleware('auth:sanctum')->group(function () {
            Route::get('/', [DiagnosisController::class, 'index']);
            Route::post('/{health_id}/respond', [DiagnosisController::class, 'respond']);
            Route::get('/{diagnosis_id}', [DiagnosisController::class, 'show']);
            Route::post('/{diagnosis_id}/follow-up', [DiagnosisController::class, 'followUp']);
            Route::get('/{diagnosis_id}/pdf', [DiagnosisController::class, 'downloadPdf']);
            Route::get('/alerts', [DiagnosisController::class, 'alerts']);
        });

        Route::prefix('actions')->middleware('auth:sanctum')->group(function () {
            Route::get('/diagnosis/{diagnosis_id}', [VetActionController::class, 'index']);
            Route::post('/diagnosis/{diagnosis_id}', [VetActionController::class, 'store']);
            Route::put('/{action_id}', [VetActionController::class, 'update']);
            Route::post('/{action_id}/paid', [VetActionController::class, 'markPaid']);
            Route::post('/{action_id}/recovery', [VetActionController::class, 'recordRecovery']);
            Route::get('/{action_id}/pdf', [VetActionController::class, 'downloadPdf']);
            Route::get('/summary', [VetActionController::class, 'summary']);
        });

        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/my-appointments', [AppointmentController::class, 'farmerAppointments']);

        // VET: Calendar & manage
        Route::prefix('vet')->group(function () {
            Route::get('/calendar', [AppointmentController::class, 'vetCalendar']);
            Route::post('/appointments/{appointment_id}', [AppointmentController::class, 'vetRespond']);
            Route::post('/appointments/{appointment_id}/checkin', fn($req, $id) => app(AppointmentController::class)->vetCheckInOut($req->merge(['action' => 'start']), $id));
            Route::post('/appointments/{appointment_id}/checkout', fn($req, $id) => app(AppointmentController::class)->vetCheckInOut($req->merge(['action' => 'end']), $id));
        });

        // SHARED
        Route::get('/appointments/{appointment_id}/pdf', [AppointmentController::class, 'downloadPdf']);
        Route::get('/vets/{vet_id}/availability', [AppointmentController::class, 'vetAvailability']);
        // VET: Manage vaccinations
        Route::prefix('vet/vaccinations')->group(function () {
            Route::get('/', [VaccinationController::class, 'index']);
            Route::post('/', [VaccinationController::class, 'store']);
            Route::post('/{schedule_id}/complete', [VaccinationController::class, 'complete']);
            Route::get('/reminders', [VaccinationController::class, 'reminders']);
            Route::post('/bulk', [VaccinationController::class, 'bulkUpload']);
            Route::get('/{schedule_id}/certificate', [VaccinationController::class, 'certificate']);
        });

        // FARMER: View history
        Route::get('/my-vaccinations', [VaccinationController::class, 'farmerHistory']);
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
            // Region & District lookup
            Route::get('/regions', [LocationController::class, 'indexRegions']);
            Route::get('/districts', [LocationController::class, 'indexDistricts']);
            Route::post('/districts', [LocationController::class, 'storeDistrict']);
            Route::get('/regions/{region_id}/districts', [LocationController::class, 'indexDistricts']);

            // Ward CRUD
            Route::prefix('wards')->group(function () {
                Route::get('/', [LocationController::class, 'indexWards']);
                Route::post('/', [LocationController::class, 'storeWard']);
                Route::get('/{id}', [LocationController::class, 'showWard']);
                Route::match(['put', 'patch'], '/{id}', [LocationController::class, 'updateWard']);
                Route::delete('/{id}', [LocationController::class, 'destroyWard']);
            });

            Route::get('/districts/{district_id}/wards', [LocationController::class, 'indexWards']);
            Route::get('/regions/{region_id}/wards', [LocationController::class, 'indexWards']);

            // User Location Assignment - MOVE THIS UP BEFORE THE WILDCARD ROUTE
            Route::prefix('user-locations')->group(function () {
                Route::get('/', [LocationController::class, 'indexUserLocations']);
                Route::post('/', [LocationController::class, 'assignUserLocation']);
                Route::patch('/{id}/primary', [LocationController::class, 'setPrimaryLocation']);
                Route::delete('/{id}', [LocationController::class, 'removeUserLocation']);
            });

            // General Location CRUD (Address + GPS)
            Route::get('/', [LocationController::class, 'indexLocations']);
            Route::post('/', [LocationController::class, 'storeLocation']);

            Route::get('/{id}', [LocationController::class, 'showLocation']);
        });
    }); // End auth:sanctum
}); // End v1 prefix
