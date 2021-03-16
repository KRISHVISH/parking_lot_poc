<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::namespace('api\v1')->group(function () {

    Route::prefix('/user')->group(function () {
        Route::post('login', 'LoginController@login');
        Route::post('register', 'RegisterController@register');
        Route::get('invalidUser', function () {
            return response(["message" => "Authentication is failed. login to continue!"]);
        })->name('invalidUser');
    });

    Route::middleware(['auth:api'])->group(function () {
        Route::get('/totalRegisteredusers', 'UserController@index');
        Route::post('/updateParkingLots', 'UserController@updateParkingSlots');
        Route::post('/bookparkingSlot', 'UserController@bookParkingSlot');
        Route::get('/getAvailableParkingSlots', 'UserController@getAvailableParkingSlots');
        Route::get('/getOccupiedParkingSlots', 'UserController@getOccupiedParkingSlots');
    });
});
