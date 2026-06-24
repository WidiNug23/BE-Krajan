<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/test-email', function () {
    Mail::to("mahesasejati95@gmail.com")->send(new SendOtpMail("Mahesa", "123456", now()->addMinutes(5)));
    return "Email OTP terkirim!";
});

Route::get('/', function () {
    return view('welcome');
});
