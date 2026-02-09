<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PropertiesController;
use App\Http\Controllers\Api\BookingsController;

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
    Route::prefix('bookings')->group(function () {
        Route::get('checkavail', [BookingsController::class, 'checkAvail']);
        Route::get('nightsbridgebookings', [BookingsController::class, 'nightsbridgeBookings']);
        Route::get('nightsbridgebookingsall', [BookingsController::class, 'nightsbridgeBookingsAll']);
        Route::get('nightsbridgebookingslist', [BookingsController::class, 'nightsbridgeBookingsList']);
        Route::post('ownerbooking', [BookingsController::class, 'ownerBooking']);
        Route::post('allocatebooking', [BookingsController::class, 'allocateBooking']);
        Route::post('guestbooking', [BookingsController::class, 'guestBooking']);
        Route::post('confirmbooking', [BookingsController::class, 'confirmBooking']);
        Route::post('cancelbooking', [BookingsController::class, 'cancelBooking']);
        Route::get('guestdetails', [BookingsController::class, 'guestDetails']);
        Route::post('updateguestdetails', [BookingsController::class, 'UpdateGuestDetails']);
        Route::get('get-billing-data/{booking_id}', [BookingsController::class, 'getBillingData']);
        Route::get('get-mails/{booking_id}', [BookingsController::class, 'getMails']);
        Route::post('send-mail/{id}', [BookingsController::class, 'sendMail']);
        Route::get('getreflist/{booking_ref}', [BookingsController::class, 'getReflist']);
        Route::post('requestchanges', [BookingsController::class, 'requestChanges']);
        Route::post('confirmchanges', [BookingsController::class, 'confirmChanges']);
        Route::get('getchanges', [BookingsController::class, 'getChanges']);
        Route::post('raiseso/{id}', [BookingsController::class, 'RaiseSO']);
        Route::post('linkso/{id}', [BookingsController::class, 'linkSo']);
        Route::get('nightsbridgeupdate/{id}', [BookingsController::class, 'NightsbridgeUpdate']);
        Route::get('allbookings', [BookingsController::class, 'allbookings']);
        Route::post('mailbookingserror', [BookingsController::class, 'MailBookingError']);
        Route::post('makepayment', [BookingsController::class, 'MakePayment']);
        Route::post('confirmpayment', [BookingsController::class, 'ConfirmPayment']);
        Route::get('getstatements/{userid}', [BookingsController::class, 'GetStatements']);
        Route::get('sendstatements/{id}', [BookingsController::class, 'SendStatements']);
        Route::get('updatestatements/{id}', [BookingsController::class, 'UpdateStatements']);
        Route::get('cancelledquotes', [BookingsController::class, 'getCancelledQuotes']);
    });

    Route::prefix('linkbookings')->group(function () {
        Route::post('linkbooking', [BookingsController::class, 'linkBooking']);
    });

    Route::resource('bookings', BookingsController::class);

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
