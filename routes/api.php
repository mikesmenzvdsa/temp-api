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

Route::prefix('v2')->group(function () {
    Route::prefix('properties')->group(function () {
        Route::get('serp', [PropertiesController::class, 'serp']);
        Route::get('single/{id}', [PropertiesController::class, 'getSingleProperty']);
        Route::put('update-owner-details/{id}', [PropertiesController::class, 'updateOwnerDetails']);
        Route::post('upload-owner-documents/{id}', [PropertiesController::class, 'uploadOwnerDocuments']);
        Route::put('uploadpdf/{id}', [PropertiesController::class, 'uploadpdf']);
        Route::put('update/{id}', [PropertiesController::class, 'update']);
        Route::put('get-images/{id}', [PropertiesController::class, 'getImages']);
        Route::post('onclicklive/{id}', [PropertiesController::class, 'onClickLive']);
        Route::post('onclicklock/{id}', [PropertiesController::class, 'onClickLock']);
        Route::delete('delete/{id}', [PropertiesController::class, 'destroy']);
        Route::delete('delete-owner-document/{documentId}', [PropertiesController::class, 'deleteOwnerDocuments']);
        Route::post('sync-with-rentals-united', [PropertiesController::class, 'syncWithRentalsUnited']);
        Route::post('update-configuration/{id}', [PropertiesController::class, 'updateConfiguration']);
        Route::get('get-configuration/{id}', [PropertiesController::class, 'getConfiguration']);
        Route::get('get-rentals-united-properties', [PropertiesController::class, 'getRentalsUnitedProperties']);
        Route::get('gettypes', [PropertiesController::class, 'getTypes']);
        Route::get('sendwelcomeletterpreview', [PropertiesController::class, 'sendWelcomeLetterPreview']);
        Route::get('syncratesavail/{id}', [PropertiesController::class, 'SyncRatesAvail']);
    });

    Route::resource('properties', PropertiesController::class);
});
