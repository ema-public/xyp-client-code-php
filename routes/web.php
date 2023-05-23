<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\HomeController;

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
    // return view('welcome');
    return view('index', ['result' => null]);
});

Route::post('/service', 'HomeController@xypClientSignature')->name('service');
Route::post('/otp', 'HomeController@otpApprove')->name('otp');
Route::post('/clientOTP', 'HomeController@xypClientOTP')->name('clientOTP');

