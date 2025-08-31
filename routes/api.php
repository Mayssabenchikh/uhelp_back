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

// Route de vérification (DOIT être disponible et nommée: verification.verify)
// Important: ne pas mettre auth:sanctum ici — protection par 'signed' + throttle
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// Route pour renvoyer le lien de vérification (l'utilisateur doit être authentifié)
Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.send');

// Route de test d'email (publique, utile pour dev / MailHog)
Route::post('/test-email', function (Request $request) {
    $email = $request->input('email');

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return response()->json(['message' => 'Email invalide'], 422);
    }

    try {
        Mail::raw("Ceci est un test d'email via Laravel.", function ($message) use ($email) {
            $message->to($email)
                    ->subject('Test Mail Laravel');
            // From explicite si tu veux forcer:
            // $message->from(config('mail.from.address'), config('mail.from.name') ?? 'UHelp');
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

    // Users (admin tools / listing)
    Route::apiResource('users', UserController::class)->except(['create','edit']);

    // Agents: liste publique pour auth, création uniquement pour admin
    Route::get('/agents', [AgentController::class, 'index']);
    Route::post('/agents', [AgentController::class, 'store'])->middleware('admin');

    // Tickets & réponses (nested resource, responses shallow)
    Route::apiResource('tickets', TicketController::class);
    Route::apiResource('tickets.responses', TicketResponseController::class)->shallow();

    // Feedbacks
    Route::post('/tickets/{ticket}/feedback', [FeedbackController::class, 'store']);
    Route::get('/tickets/{ticket}/feedback', [FeedbackController::class, 'show']);

    // Departments
    Route::apiResource('departments', DepartmentController::class);
    Route::get('departments/{department}/tickets', [TicketController::class, 'ticketsByDepartment']);

    // Schedules (horaires) par agent
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

    // Subscriptions & payments (admin or allowed users)
    Route::apiResource('subscription-plans', SubscriptionPlanController::class);
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::apiResource('payments', PaymentController::class);

    // Chat / Conversations / Attachments
    Route::post('/conversations', [ChatController::class, 'createConversation']);
    Route::get('/chat/{conversation}/messages', [ChatController::class, 'getMessages']);
    Route::post('chat/send', [ChatController::class, 'send']);
    Route::get('conversations/{conversationId}/messages', [ChatController::class, 'messages']);

    Route::post('attachments', [AttachmentController::class, 'store']);
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy']);
    Route::get('attachments/{attachment}/show', [AttachmentController::class, 'show']);

    // Groups
    Route::apiResource('groups', GroupController::class);
    Route::post('groups/{group}/add-member', [GroupController::class, 'addMember']);
    Route::post('groups/{group}/remove-member', [GroupController::class, 'removeMember']);

    // Dashboard & helpers
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assignAgent']);

    // Me
    Route::get('/me', [UserController::class, 'me']);
});

/*
|--------------------------------------------------------------------------
| Admin-only routes
|--------------------------------------------------------------------------
|
| Utilise middleware role:admin (auth déjà requis) pour l'admin area
|
*/

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('admin/invoices', InvoiceController::class);
    Route::get('admin/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
});
