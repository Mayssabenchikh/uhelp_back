<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request; 
use App\Services\InvoiceService;
use App\Events\PaymentCompleted;

class PaymentController extends Controller
{
    /**
     * Liste des paiements avec recherche côté serveur
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $query = Payment::with(['subscription.plan'])
            ->where('user_id', $user->id);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('subscription.plan', function ($planQuery) use ($search) {
                    $planQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhere('amount', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderByDesc('created_at')->get()
        );
    }

    /**
     * Création d'un paiement et initialisation Konnect
     */
    public function store(StorePaymentRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $data = $request->validated();

        // Bloquer si l'utilisateur a déjà une subscription active
        $hasActive = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('current_period_ends_at')
                  ->orWhere('current_period_ends_at', '>', now());
            })
            ->exists();

        if ($hasActive) {
            return response()->json(['message' => 'Vous avez déjà une souscription active.'], 422);
        }

        // Déterminer la souscription
        if (!empty($data['subscription_id'])) {
            $subscription = Subscription::where('id', $data['subscription_id'])
                ->where('user_id', $user->id)
                ->first();

            if (!$subscription) {
                return response()->json(['message' => 'Subscription not found'], 404);
            }

            if (empty($data['amount']) && $subscription->plan) {
                $data['amount'] = ($data['currency'] ?? 'TND') === 'TND'
                    ? (int) round($subscription->plan->price * 1000)
                    : (int) round($subscription->plan->price * 100);
            }
        } else {
            if (!empty($data['subscription_plan_id'])) {
                $plan = SubscriptionPlan::find($data['subscription_plan_id']);
                if (!$plan) {
                    return response()->json(['message' => 'Plan introuvable'], 404);
                }

                if (empty($data['amount'])) {
                    $data['amount'] = ($data['currency'] ?? 'TND') === 'TND'
                        ? (int) round($plan->price * 1000)
                        : (int) round($plan->price * 100);
                }

                $subscription = Subscription::where('user_id', $user->id)
                    ->where('subscription_plan_id', $data['subscription_plan_id'])
                    ->where('status', 'pending')
                    ->first();

                if (!$subscription) {
                    $subscription = Subscription::create([
                        'user_id' => $user->id,
                        'subscription_plan_id' => $data['subscription_plan_id'],
                        'status' => 'pending',
                        'tickets_used' => 0,
                    ]);
                }
            } else {
                return response()->json(['message' => 'subscription_id or subscription_plan_id is required'], 422);
            }
        }

        if (empty($data['amount'])) {
            return response()->json([
                'message' => 'Amount is required or subscription_plan_id must provide a price'
            ], 422);
        }

        $data['user_id'] = $user->id;
        $data['status'] = 'pending';
        $data['subscription_id'] = $subscription->id;

        DB::beginTransaction();
        try {
            $payment = Payment::create($data);

            $konnectBase = config('services.konnect.api_base', 'https://api.sandbox.konnect.network');
            $url = rtrim($konnectBase, '/') . '/api/v2/payments/init-payment';

            $body = [
                'receiverWalletId' => config('services.konnect.wallet_id'),
                'token' => $data['currency'],
                'amount' => $data['amount'],
                'type' => 'immediate',
                'description' => $data['description'] ?? 'Payment for subscription',
                'webhook' => config('services.konnect.callback_url'),
                'orderId' => 'sub_' . $subscription->id . '_pay_' . $payment->id,
                'checkoutForm' => true,
            ];

            $konnectResponse = Http::withHeaders([
                'x-api-key' => config('services.konnect.api_key'),
                'Content-Type' => 'application/json',
            ])->post($url, $body);

            if ($konnectResponse->failed()) {
                DB::rollBack();
                Log::error('Konnect init-payment failed', [
                    'response' => $konnectResponse->body()
                ]);
                return response()->json([
                    'message' => 'Erreur lors de la création du paiement Konnect',
                    'details' => $konnectResponse->json()
                ], 500);
            }

            $konnectData = $konnectResponse->json();

            $payment->update([
                'provider_payment_id' => $konnectData['payment']['id']
                    ?? $konnectData['paymentRef']
                    ?? null
            ]);

            DB::commit();

            return response()->json([
                'payment' => $payment->load('subscription.plan', 'user'),
                'konnect' => $konnectData,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Payment::store error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur serveur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Téléchargement de la facture PDF
     */
    public function downloadInvoice(Payment $payment)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($payment->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $invoice = $payment->invoice;
        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        $invoice->load('items', 'client', 'admin');

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Mise à jour du statut d'un paiement + dispatch événement
     */
    public function update(Request $request, Payment $payment, InvoiceService $invoiceService)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,failed'
        ]);

        $payment->status = $request->status;
        $payment->save();

        if ($payment->status === 'completed') {
            // Déclenche l'événement pour que CreateInvoiceForPayment s'exécute et envoie l'email
            event(new PaymentCompleted($payment));

            // Fallback : génération directe si nécessaire
            if (!$payment->invoice) {
                $invoiceService->generateForPayment($payment);
            }
        }

        return response()->json([
            'message' => 'Payment updated successfully',
            'payment' => $payment->load('invoice')
        ]);
    }
}
