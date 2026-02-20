<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PropertiesController;
use App\Http\Controllers\Api\BookingsController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\ChannelsController;
use App\Http\Controllers\Api\ListingTypesController;
use App\Http\Controllers\Api\LocationsController;
use App\Http\Controllers\Api\FeaturesController;
use App\Http\Controllers\Api\EstablishmentDetailsController;
use App\Http\Controllers\Api\ExtraChargesController;
use App\Http\Controllers\Api\InventoriesController;
use App\Http\Controllers\Api\LaundriesController;
use App\Http\Controllers\Api\OperationalInformationController;
use App\Http\Controllers\Api\PropertyManagerFeesController;
use App\Http\Controllers\Api\ReportedIssuesController;
use App\Http\Controllers\Api\ReviewsController;
use App\Http\Controllers\Api\WelcomePacksController;
use App\Http\Controllers\Api\TasksController;
use App\Http\Controllers\Api\ErrorLogsController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ReservationsController;
use Illuminate\Support\Facades\Log;

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

Route::options('{any}', function () {
    return response()
        ->json([], 204)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', '*');
})->where('any', '.*');

Route::prefix('v2')->group(function () {
    Route::resource('channels', ChannelsController::class);
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::prefix('stats')->group(function () {
        Route::get('getturnover', [StatsController::class, 'getTurnover']);
    });
    Route::prefix('locations')->group(function () {
        Route::get('serp', [LocationsController::class, 'serp']);
    });

    Route::resource('locations', LocationsController::class);
    Route::resource('listingtypes', ListingTypesController::class);

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

    // Route::resource('product', ProductController::class);
    Route::prefix('product')->group(function () {
        Route::get('dashboard', [ProductController::class, 'dashboard']);
        Route::get('check-in-rules', [ProductController::class, 'index']);
        Route::post('bodycorp/deleted/{id}', [ProductController::class, 'destroy']);
        Route::post('bodycorp/edit/{id}', [ProductController::class, 'updateBodyCorp']);
        Route::post('add-bodycorp', [ProductController::class, 'storeBodyCorp']);
        Route::put('update-owner-details/{id}', [ProductController::class, 'updateOwnerDetails']);
        Route::delete('delete/{id}', [ProductController::class, 'destroy']);
    });

    Route::prefix('reservations')->group(function () {
        Route::get('dashboard', [ReservationsController::class, 'index']);
        Route::get('collect', [ReservationsController::class, 'collect']);
        Log::debug("Reached");
        Route::post('collect/insert', [ReservationsController::class, 'storeGuestBooking']);
        Route::post('collect/update/{id}', [ReservationsController::class, 'updateGuestBooking']);
        Route::get('collected', [ReservationsController::class, 'collected']);
        Route::get('sent', [ReservationsController::class, 'sentTobodyCorp']);
        Route::get('past-completed', [ReservationsController::class, 'pastCompleted']);
        Route::get('past-incompleted', [ReservationsController::class, 'pastIncompleted']);
    });

    Route::resource('features', FeaturesController::class);
    Route::resource('extra-charges', ExtraChargesController::class);
    Route::resource('property-manager-fees', PropertyManagerFeesController::class);

    Route::prefix('inventory')->group(function () {
        Route::post('createcategory', [InventoriesController::class, 'createCategory']);
        Route::post('updatecategory', [InventoriesController::class, 'updateCategory']);
        Route::post('linkcategoryproperty', [InventoriesController::class, 'linkCategoryProperty']);
        Route::get('getcategory', [InventoriesController::class, 'getCategory']);
        Route::post('createitem', [InventoriesController::class, 'createItem']);
        Route::post('updateitem', [InventoriesController::class, 'updateItem']);
        Route::post('linkitemcategory', [InventoriesController::class, 'linkItemCategory']);
        Route::get('getitem', [InventoriesController::class, 'getItem']);
        Route::post('createinventoryline', [InventoriesController::class, 'createInventoryLine']);
        Route::put('updateinventory', [InventoriesController::class, 'updateInventory']);
        Route::post('updateinventoryline', [InventoriesController::class, 'updateInventoryLine']);
        Route::post('duplicateinventory', [InventoriesController::class, 'duplicateInventory']);
        Route::post('deleteinventory', [InventoriesController::class, 'deleteInventory']);
        Route::get('getinventory', [InventoriesController::class, 'getInventory']);
    });

    Route::resource('inventory', InventoriesController::class);

    Route::prefix('operational-information')->group(function () {
        Route::get('get-details/{property_id}', [OperationalInformationController::class, 'getDetails']);
        Route::post('create-details', [OperationalInformationController::class, 'store']);
        Route::put('update-details/{id}', [OperationalInformationController::class, 'update']);

        Route::get('get-extras-and-fees/{property_id}', [ExtraChargesController::class, 'show']);
        Route::put('update-extras-and-fees/{id}', [ExtraChargesController::class, 'update']);

        Route::get('get-property-manager-fees/{property_id}', [PropertyManagerFeesController::class, 'show']);
        Route::put('update-property-manager-fees/{id}', [PropertyManagerFeesController::class, 'update']);

        Route::get('get-suppliers', [OperationalInformationController::class, 'getSuppliers']);
        Route::put('update-supplier/{id}', [OperationalInformationController::class, 'updateSupplier']);
        Route::put('link-supplier/{id}', [OperationalInformationController::class, 'linkSupplier']);
        Route::get('get-approved-suppliers/{property_id}', [OperationalInformationController::class, 'getApprovedSuppliers']);

        Route::get('get-linen-invoices/{property_id}', [OperationalInformationController::class, 'getLinenInvoices']);
        Route::post('upload-linen-invoices/{property_id}', [OperationalInformationController::class, 'uploadLinenInvoices']);
        Route::put('update-linen-invoice-details/{id}', [OperationalInformationController::class, 'updateLinenInvoiceDetails']);
        Route::get('download-linen-invoice/{id}', [OperationalInformationController::class, 'downloadLinenInvoice']);
        Route::delete('delete-linen-invoice/{id}', [OperationalInformationController::class, 'deleteLinenInvoice']);

        Route::get('get-key-images/{property_id}', [OperationalInformationController::class, 'getKeyImages']);
        Route::post('upload-key-images/{property_id}', [OperationalInformationController::class, 'uploadKeyImages']);
        Route::put('update-key-details/{id}', [OperationalInformationController::class, 'updateKeyDetails']);
        Route::delete('delete-key-image/{id}', [OperationalInformationController::class, 'deleteKeyImage']);

        Route::post('add-bed/{property_id}', [OperationalInformationController::class, 'addBed']);
        Route::put('edit-bed/{id}', [OperationalInformationController::class, 'updateBed']);
        Route::delete('delete-bed/{id}', [OperationalInformationController::class, 'deleteBed']);
    });

    Route::get('laundries/dashboard/{userid}', [LaundriesController::class, 'dashboard']);
    Route::resource('laundries', LaundriesController::class)->only(['index', 'show', 'update']);

    Route::prefix('tasks')->group(function () {
        Route::get('dashboard/{userid}', [TasksController::class, 'dashboard']);
        Route::post('setdeparturearrivals', [TasksController::class, 'setDepartureArrivals']);
        Route::get('getdamageclaims', [TasksController::class, 'getDamageClaims']);
        Route::post('updatedamageclaim/{id?}', [TasksController::class, 'updateDamageClaim']);
    });

    Route::resource('tasks', TasksController::class);

    Route::resource('errorlogs', ErrorLogsController::class);

    Route::prefix('reviews')->group(function () {
        Route::get('/', [ReviewsController::class, 'index']);
        Route::put('update-review/{id}', [ReviewsController::class, 'updateReview']);
        Route::post('send-review', [ReviewsController::class, 'sendReview']);
    });

    Route::resource('welcome-packs', WelcomePacksController::class);

    Route::prefix('reported-issues/{id}')->group(function () {
        Route::post('/', [ReportedIssuesController::class, 'update']);
        Route::post('duplicate-issue', [ReportedIssuesController::class, 'duplicateIssue']);
        Route::post('allocate-user/{user_id?}', [ReportedIssuesController::class, 'allocateUser']);
        Route::put('deallocate-user', [ReportedIssuesController::class, 'deallocateUser']);
        Route::put('update-allocated-user/{user_id}', [ReportedIssuesController::class, 'updateAllocatedUser']);
        Route::post('send-mail', [ReportedIssuesController::class, 'onSendMail']);
        Route::post('send-issue-form-as-mail', [ReportedIssuesController::class, 'onSendIssueFormAsMail']);
    });

    Route::resource('reported-issues', ReportedIssuesController::class);

    Route::prefix('establishment-details')->group(function () {
        Route::get('get-prop-table-info', [EstablishmentDetailsController::class, 'getPropTableInfo']);
        Route::put('update-prop-table-info/{id}', [EstablishmentDetailsController::class, 'updatePropTableInfo']);
        Route::get('get-details/{id}', [EstablishmentDetailsController::class, 'getDetails']);
        Route::get('get-features/{id}', [EstablishmentDetailsController::class, 'getFeatures']);
        Route::put('update-features/{id}', [EstablishmentDetailsController::class, 'updateFeatures']);
        Route::get('get-photos/{id?}', [EstablishmentDetailsController::class, 'getPhotos']);
        Route::get('download-photo/{id}', [EstablishmentDetailsController::class, 'downloadPhoto']);
        Route::post('upload-photos/{id}', [EstablishmentDetailsController::class, 'uploadPhotos']);
        Route::put('update-photos/{id}', [EstablishmentDetailsController::class, 'updatePhotos']);
        Route::delete('delete-photo/{id}', [EstablishmentDetailsController::class, 'deletePhoto']);
        Route::get('get-documents/{id?}', [EstablishmentDetailsController::class, 'getDocuments']);
        Route::get('download-document/{id}', [EstablishmentDetailsController::class, 'downloadDocument']);
        Route::post('upload-documents/{id}', [EstablishmentDetailsController::class, 'uploadDocuments']);
        Route::put('update-documents/{id}', [EstablishmentDetailsController::class, 'updateDocuments']);
        Route::delete('delete-document/{id}', [EstablishmentDetailsController::class, 'deleteDocument']);
        Route::get('get-channels/{company_id}', [EstablishmentDetailsController::class, 'getChannels']);
        Route::put('update-channels/{company_id}', [EstablishmentDetailsController::class, 'updateChannels']);
        Route::get('get-specials/{property_id}', [EstablishmentDetailsController::class, 'getSpecials']);
        Route::post('create-special/{property_id}', [EstablishmentDetailsController::class, 'createSpecial']);
        Route::put('update-special/{id}', [EstablishmentDetailsController::class, 'updateSpecial']);
        Route::delete('delete-special/{id}', [EstablishmentDetailsController::class, 'deleteSpecial']);
    });

    Route::prefix('users')->group(function () {
        Route::get('authenticate', [UsersController::class, 'authenticateUser']);
    });

    Route::resource('users', UsersController::class)->only(['index', 'show']);
});
