<?php

use Illuminate\Support\Facades\Route;
use app\Http\Controllers\PropertiesController;
use App\Http\Controllers\LoginController;
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

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/me', [LoginController::class, 'me'])->middleware('auth');
Route::group(['prefix' => 'properties'], function () {

    $controller = 'VirtualDesigns\HostAgentsApi\Controllers\PropertiesController';

});

Route::resource('properties', PropertiesController::class);
