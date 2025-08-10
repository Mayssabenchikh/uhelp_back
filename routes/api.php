<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [AuthController::class, 'profile']);
    Route::post('logout', [AuthController::class, 'logout']);
});
Route::middleware(['auth:sanctum', 'admin'])->post('/agents', [AgentController::class, 'store']);

Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('tickets', TicketController::class);
});


Route::post('/test-email', function (Request $request) {
    Mail::raw('Ceci est un test d\'email via Laravel et MailHog.', function ($message) use ($request) {
        $message->to($request->email)
                ->subject('Test MailHog Laravel');
    });

    return response()->json(['message' => 'Email envoyé']);
});
