<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\TicketResponseController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\InternalNoteController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\KonnectWebhookController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TrashedTicketController;
use App\Http\Controllers\QuickResponseController;
use App\Http\Controllers\GeminiController;
use App\Http\Controllers\ReportsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes publiques
|
*/

// Auth public
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Konnect webhook public (GET/POST)
Route::post('konnect/webhook', KonnectWebhookController::class);
Route::get('konnect/webhook', KonnectWebhookController::class);

// Konnect callback (explicitement sans middleware auth)
Route::post('/konnect/callback', KonnectWebhookController::class)->withoutMiddleware(['auth:sanctum']);
Route::get('/konnect/callback', KonnectWebhookController::class)->withoutMiddleware(['auth:sanctum']);

// Email verification (signed + throttle) — NOT auth:sanctum
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.send');

// Route de test d'email (publique)
Route::post('/test-email', function (Request $request) {
    $email = $request->input('email');

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return response()->json(['message' => 'Email invalide'], 422);
    }

    try {
        Mail::raw("Ceci est un test d'email via Laravel.", function ($message) use ($email) {
            $message->to($email)
                    ->subject('Test Mail Laravel');
        });

        return response()->json(['message' => 'Email envoyé (attempt)']);
    } catch (\Throwable $e) {
        Log::error('Mail error: ' . $e->getMessage());
        Log::error($e->getTraceAsString());

        return response()->json([
            'message' => 'Envoi échoué',
            'error' => $e->getMessage()
        ], 500);
    }
});

/*
|--------------------------------------------------------------------------
| Routes protégées (auth:sanctum)
|--------------------------------------------------------------------------
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Auth actions
    Route::get('profile', [AuthController::class, 'profile']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('/me', [UserController::class, 'me']);

    // Quick responses resource
    Route::apiResource('quick-responses', QuickResponseController::class);

    // --- Chat routes ---
    Route::get('/conversations', [ChatController::class, 'index']);
    Route::get('/conversations/{id}/details', [ChatController::class, 'conversationDetails']);
    Route::post('/conversations/{id}/join', [ChatController::class, 'join']);
    Route::post('/conversations', [ChatController::class, 'createConversation']);
    Route::get('/conversations/{conversation}/messages', [ChatController::class, 'getMessages']);
    Route::get('/chat/{conversation}/messages', [ChatController::class, 'getMessages']);
    Route::post('/chat/send', [ChatController::class, 'send']);
    Route::post('/conversations/direct', [ChatController::class, 'storeDirect']);

    // Gemini endpoints
    Route::post('gemini/suggest', [GeminiController::class, 'suggest']);
    Route::post('gemini/translate', [GeminiController::class, 'translate']);
    Route::post('gemini/faq', [GeminiController::class, 'faqFromTicket']);

    // Users & exports
    Route::get('users/ticket-counts', [TicketController::class, 'ticketCounts']);
    Route::get('/users/export', [UserController::class, 'export']);
    Route::apiResource('users', UserController::class);

    // Agents
    Route::get('/agents', [AgentController::class, 'index']);
    Route::post('/agents', [AgentController::class, 'store'])->middleware('admin');

    // Trashed tickets
    Route::get('/tickets/trashed', [TrashedTicketController::class, 'index']);
    Route::post('/tickets/trashed/restore', [TrashedTicketController::class, 'bulkRestore']);
    Route::delete('/tickets/trashed', [TrashedTicketController::class, 'bulkForceDelete']);
    Route::post('/tickets/{id}/restore', [TrashedTicketController::class, 'restore']);
    Route::delete('/tickets/{id}/force', [TrashedTicketController::class, 'forceDelete']);
    Route::post('tickets/trashed/auto-clean', [TrashedTicketController::class, 'autoCleanOld']);

    // Tickets & responses
    Route::apiResource('tickets', TicketController::class);
    Route::apiResource('tickets.responses', TicketResponseController::class)->shallow();

    // Feedbacks
    Route::post('/tickets/{ticket}/feedback', [FeedbackController::class, 'store']);
    Route::get('/tickets/{ticket}/feedback', [FeedbackController::class, 'show']);

    // Departments
    Route::apiResource('departments', DepartmentController::class);
    Route::get('departments/{department}/tickets', [TicketController::class, 'ticketsByDepartment']);

    // Schedules
    Route::get('agents/{agent}/schedules', [ScheduleController::class, 'index']);
    Route::post('agents/{agent}/schedules', [ScheduleController::class, 'store']);
    Route::get('schedules/{schedule}', [ScheduleController::class, 'show']);
    Route::put('schedules/{schedule}', [ScheduleController::class, 'update']);
    Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy']);

    // Internal notes
    Route::get('tickets/{ticket}/internal-notes', [InternalNoteController::class, 'index']);
    Route::post('tickets/{ticket}/internal-notes', [InternalNoteController::class, 'store']);
    Route::get('internal-notes/{internalNote}', [InternalNoteController::class, 'show']);
    Route::put('internal-notes/{internalNote}', [InternalNoteController::class, 'update']);
    Route::delete('internal-notes/{internalNote}', [InternalNoteController::class, 'destroy']);

    // Subscriptions & payments
    Route::apiResource('subscription-plans', SubscriptionPlanController::class);
    Route::apiResource('subscriptions', SubscriptionController::class);
    // compatibility routes for front
Route::post('subscriptions/{plan}/subscribe', [SubscriptionController::class, 'subscribe']);
Route::post('user/subscription/cancel', [SubscriptionController::class, 'cancelCurrent']);

    Route::apiResource('payments', PaymentController::class);
    Route::get('payments/{payment}/invoice/download', [PaymentController::class, 'downloadInvoice']);
Route::put('/payments/{payment}', [PaymentController::class, 'update']);
Route::post('gemini/faq', [GeminiController::class, 'generateFaq']);

    // Attachments
    Route::post('attachments', [AttachmentController::class, 'store']);
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy']);
    Route::get('attachments/{attachment}/show', [AttachmentController::class, 'show']);


    Route::post('/user/subscription/cancel', [SubscriptionController::class, 'cancel']);

    // Groups
    Route::apiResource('groups', GroupController::class);
    Route::post('groups/{group}/add-member', [GroupController::class, 'addMember']);
    Route::post('groups/{group}/remove-member', [GroupController::class, 'removeMember']);

    // Dashboard & helpers
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assignAgent']);
Route::get('payments/{payment}/invoice/download', [PaymentController::class, 'downloadInvoice']);

    // ------------------ REPORTS ------------------
    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/export', [ReportsController::class, 'export'])->name('reports.export');
    });
});


/*
|--------------------------------------------------------------------------
| Admin-only routes
|--------------------------------------------------------------------------
|
*/

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('admin/invoices', InvoiceController::class);
    Route::get('admin/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
});
Route::middleware('auth:sanctum')->put('/user/{id}', [UserController::class, 'update']);
