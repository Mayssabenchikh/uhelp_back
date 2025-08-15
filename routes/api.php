<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\TicketResponseController;
use App\Http\Middleware\AdminMiddleware;
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
Route::get('/tickets', [TicketController::class, 'index']);
Route::get('/tickets/{id}', [TicketController::class, 'show']);

Route::post('/test-email', function (Request $request) {
    Mail::raw('Ceci est un test d\'email via Laravel et MailHog.', function ($message) use ($request) {
        $message->to($request->email)
                ->subject('Test MailHog Laravel');
    });

    return response()->json(['message' => 'Email envoyé']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Nested + shallow : index/store utilisent {ticket}, show/update/destroy utilisent {response}
    Route::apiResource('tickets.responses', TicketResponseController::class)->shallow();
});


Route::apiResource('departments', DepartmentController::class);
Route::get('departments/{department}/tickets', [TicketController::class, 'ticketsByDepartment']);
use App\Http\Controllers\ScheduleController;

// CRUD horaires par agent
Route::get('agents/{agent}/schedules', [ScheduleController::class, 'index']);
Route::post('agents/{agent}/schedules', [ScheduleController::class, 'store']);
Route::get('schedules/{schedule}', [ScheduleController::class, 'show']);
Route::put('schedules/{schedule}', [ScheduleController::class, 'update']);
Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy']);
use App\Http\Controllers\InternalNoteController;

// CRUD notes internes par ticket
Route::get('tickets/{ticket}/internal-notes', [InternalNoteController::class, 'index']);
Route::post('tickets/{ticket}/internal-notes', [InternalNoteController::class, 'store']);
Route::get('internal-notes/{internalNote}', [InternalNoteController::class, 'show']);
Route::put('internal-notes/{internalNote}', [InternalNoteController::class, 'update']);
Route::delete('internal-notes/{internalNote}', [InternalNoteController::class, 'destroy']);
