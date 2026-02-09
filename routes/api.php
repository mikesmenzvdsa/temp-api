<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PropertiesController;

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

Route::prefix('properties')->group(function () {
    Route::get('/', [PropertiesController::class, 'index']);
    Route::get('/user/{userid}', [PropertiesController::class, 'show']);
    Route::get('/{id}/detail', [PropertiesController::class, 'getSingleProperty']);
    Route::post('/', [PropertiesController::class, 'store']);
    Route::put('/{id}', [PropertiesController::class, 'update']);
    Route::get('/types', [PropertiesController::class, 'getTypes']);
    Route::get('/{id}/configuration', [PropertiesController::class, 'getConfiguration']);
    Route::put('/{id}/configuration', [PropertiesController::class, 'updateConfiguration']);
    Route::put('/{id}/owner', [PropertiesController::class, 'updateOwnerDetails']);
    Route::post('/{id}/owner-documents', [PropertiesController::class, 'uploadOwnerDocuments']);
    Route::delete('/owner-documents/{documentId}', [PropertiesController::class, 'deleteOwnerDocuments']);
    Route::get('/serp', [PropertiesController::class, 'serp']);
    Route::put('/{id}/live', [PropertiesController::class, 'onClickLive']);
    Route::put('/{id}/lock', [PropertiesController::class, 'onClickLock']);
    Route::post('/rentals-united/sync', [PropertiesController::class, 'syncWithRentalsUnited']);
    Route::get('/rentals-united', [PropertiesController::class, 'getRentalsUnitedProperties']);
    Route::post('/welcome-letter-preview', [PropertiesController::class, 'sendWelcomeLetterPreview']);
    Route::post('/{id}/sync-rates', [PropertiesController::class, 'SyncRatesAvail']);
});
