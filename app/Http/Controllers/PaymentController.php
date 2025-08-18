<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthenticated'], 401);

        $data = $request->validated();

        // Bloquer si l'utilisateur a déjà une subscription active
        $hasActive = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function($q){
                $q->whereNull('current_period_ends_at')->orWhere('current_period_ends_at', '>', now());
            })
            ->exists();

        if ($hasActive) {
            return response()->json(['message' => 'Vous avez déjà une souscription active.'], 422);
        }

        // Récupérer le plan et calculer le montant si nécessaire
        if (!empty($data['subscription_plan_id'])) {
            $plan = SubscriptionPlan::find($data['subscription_plan_id']);
            if (!$plan) return response()->json(['message' => 'Plan introuvable'], 404);

            if (empty($data['amount'])) {
                $data['amount'] = ($data['currency'] ?? 'TND') === 'TND'
                    ? (int) round($plan->price * 1000) // millimes
                    : (int) round($plan->price * 100); // centimes
            }
        }

        if (empty($data['amount'])) {
            return response()->json(['message' => 'Amount is required or subscription_plan_id must provide a price'], 422);
        }

        $data['user_id'] = $user->id;
        $data['status'] = 'pending';

        DB::beginTransaction();
        try {
            // Créer la subscription "pending"
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $data['subscription_plan_id'] ?? null,
                'status' => 'pending',
                'tickets_used' => 0,
            ]);

            $data['subscription_id'] = $subscription->id;

            // Créer le paiement local "pending"
            $payment = Payment::create($data);

            // Initier le paiement Konnect
            $konnectBase = config('services.konnect.api_base', 'https://api.sandbox.konnect.network');
            $url = rtrim($konnectBase, '/') . '/api/v2/payments/init-payment';

            $body = [
                'receiverWalletId' => config('services.konnect.wallet_id'),
                'token' => $data['currency'],
                'amount' => $data['amount'],
                'type' => 'immediate',
                'description' => $data['description'] ?? 'Payment for subscription',
                'webhook' => config('services.konnect.callback_url'),
                'orderId' => 'sub_'.$subscription->id.'_pay_'.$payment->id,
                'checkoutForm' => true,
            ];

            $konnectResponse = Http::withHeaders([
                'x-api-key' => config('services.konnect.api_key'),
                'Content-Type' => 'application/json',
            ])->post($url, $body);

            if ($konnectResponse->failed()) {
                DB::rollBack();
                Log::error('Konnect init-payment failed', ['response' => $konnectResponse->body()]);
                return response()->json([
                    'message' => 'Erreur lors de la création du paiement Konnect',
                    'details' => $konnectResponse->json()
                ], 500);
            }

            $konnectData = $konnectResponse->json();

            // Stocker correctement l'ID Konnect
            if (!empty($konnectData['payment']['id'])) {
                $payment->update([
                    'provider_payment_id' => $konnectData['payment']['id']
                ]);
            } else {
                // fallback si Konnect ne renvoie pas l'id
                $payment->update([
                    'provider_payment_id' => $konnectData['paymentRef'] ?? null
                ]);
            }

            DB::commit();

            return response()->json([
                'payment' => $payment->load('subscription','user'),
                'konnect' => $konnectData,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Payment::store error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur serveur', 'error' => $e->getMessage()], 500);
        }
    }
}
