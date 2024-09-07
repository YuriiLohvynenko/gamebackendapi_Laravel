<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// header('Access-Control-Allow-Origin : *');
// header('Access-Control-Allow-Headers : Content-Type,X-Auth-Token,Authorization,Origin');
// header('Access-Control-Allow-Methods :GET, POST, PUT, DELETE, OPTIONS');

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', 'Auth\RegisterController@register')->name('auth');
Route::post('/checkuser', 'Auth\LoginController@checkUser')->name('auth');
Route::post('/chgPassword', 'Auth\LoginController@chgPassword');
Route::post('/login', 'Auth\LoginController@login')->name('auth');
Route::post('/withdraw', 'Main\WithdrowController@Withdrow')->name('main');
Route::post('/transfer', 'Main\TransferController@Transfer')->name('main');
Route::post('/coupon', 'Main\CouponController@getCoupon')->name('main');
Route::post('/checkcredit', 'Main\CheckController@getCheckcredit')->name('main');
Route::post('/profile', 'Main\ProfileController@getProfile')->name('main');
Route::get('/promotion', 'Main\PromotionController@getPromotion')->name('auth');
Route::get('/bankv2', 'Main\BankController@getBankv2')->name('main');
Route::post('/history-data', 'Main\HistoryController@getHistoryv2')->name('main');
Route::get('/alert', 'Main\AlertController@Alert')->name('main');
Route::post('/truewallet', 'Main\TruewalletController@Truewallet')->name('main');
