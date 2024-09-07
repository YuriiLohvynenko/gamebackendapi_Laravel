<?php

use Illuminate\Support\Facades\Route;

// header('Access-Control-Allow-Origin : *');
// header('Access-Control-Allow-Headers : Content-Type,X-Auth-Token,Authorization,Origin');
// header('Access-Control-Allow-Methods :GET, POST, PUT, DELETE, OPTIONS');

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

Route::get('/clear', function() {

  Artisan::call('cache:clear');
  Artisan::call('config:clear');
  Artisan::call('config:cache');
  Artisan::call('view:clear');

  return "Cleared!";

});