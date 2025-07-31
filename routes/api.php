<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthConroller;
Route::post('register', [AuthConroller::class, 'register']);
Route::post('login', [AuthConroller::class, 'login']);
Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('profile', [AuthConroller::class, 'profile']);
    Route::post('logout', [AuthConroller::class, 'logout']);
});
//Route::get('/user', function (Request $request) {
   // return $request->user();
//})->middleware('auth:sanctum');
