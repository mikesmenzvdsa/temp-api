<?php

use Illuminate\Support\Facades\Route;
use app\Http\Controllers\PropertiesController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::group(['prefix' => 'properties'], function () {

    $controller = 'VirtualDesigns\HostAgentsApi\Controllers\PropertiesController';

});

Route::resource('properties', PropertiesController::class);
