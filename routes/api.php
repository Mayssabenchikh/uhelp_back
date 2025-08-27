<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\TicketResponseController;
use Illuminate\Http\Request;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\InternalNoteController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\KonnectWebhookController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FeedbackController;

use App\Http\Controllers\Admin\InvoiceController;
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// webhook public (Konnect calls it). On utilise token optionnel dans la query string ou header.
Route::post('konnect/webhook', App\Http\Controllers\KonnectWebhookController::class);
Route::get('konnect/webhook', App\Http\Controllers\KonnectWebhookController::class); // Konnect peut envoyer GET selon doc

Route::post('/konnect/callback', KonnectWebhookController::class)
    ->withoutMiddleware(['auth:sanctum']); // désactive auth pour ce webhook
Route::get('/konnect/callback', KonnectWebhookController::class)
    ->withoutMiddleware(['auth:sanctum']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [AuthController::class, 'profile']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::middleware('admin')->post('/agents', [AgentController::class, 'store']);

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::post('/tickets/{ticket}/feedback', [FeedbackController::class, 'store']);
    Route::get('/tickets/{ticket}/feedback', [FeedbackController::class, 'show']);
    Route::apiResource('tickets', TicketController::class);
    Route::apiResource('tickets.responses', TicketResponseController::class)->shallow();

    Route::apiResource('departments', DepartmentController::class);
    Route::get('departments/{department}/tickets', [TicketController::class, 'ticketsByDepartment']);

    // CRUD horaires par agent
    Route::get('agents/{agent}/schedules', [ScheduleController::class, 'index']);
    Route::post('agents/{agent}/schedules', [ScheduleController::class, 'store']);
    Route::get('schedules/{schedule}', [ScheduleController::class, 'show']);
    Route::put('schedules/{schedule}', [ScheduleController::class, 'update']);
    Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy']);

    // CRUD notes internes par ticket
    Route::get('tickets/{ticket}/internal-notes', [InternalNoteController::class, 'index']);
    Route::post('tickets/{ticket}/internal-notes', [InternalNoteController::class, 'store']);
    Route::get('internal-notes/{internalNote}', [InternalNoteController::class, 'show']);
    Route::put('internal-notes/{internalNote}', [InternalNoteController::class, 'update']);
    Route::delete('internal-notes/{internalNote}', [InternalNoteController::class, 'destroy']);

    // Subscription / Payment administration by authenticated users
    Route::apiResource('subscription-plans', SubscriptionPlanController::class);
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::apiResource('payments', PaymentController::class);
});

Route::post('/test-email', function (Request $request) {
    Mail::raw('Ceci est un test d\'email via Laravel et MailHog.', function ($message) use ($request) {
        $message->to($request->email)
                ->subject('Test MailHog Laravel');
    });

    return response()->json(['message' => 'Email envoyé']);
});



Route::middleware(['auth:sanctum','role:admin'])->group(function () {
    Route::apiResource('admin/invoices', InvoiceController::class);
    Route::get('admin/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
});
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed','throttle:6,1']) // signed vérifie la signature ; throttle pour limiter les abus
    ->name('verification.verify');

// Pour renvoyer le mail, garde auth (car seul user authentifié peut demander renvoi)
Route::post('/email/verification-notification', [EmailVerificationController::class,'resend'])
    ->middleware(['auth:sanctum','throttle:6,1'])
    ->name('verification.send');


