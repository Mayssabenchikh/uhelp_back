<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Events\PaymentCompleted;

class KonnectWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        // Récupérer payment_ref depuis query ou body
        $paymentRef = $request->query('payment_ref')
                    ?? $request->query('paymentRef')
                    ?? $request->input('payment_ref')
                    ?? $request->input('paymentRef');

        if (!$paymentRef) {
            Log::warning('Konnect webhook: missing payment_ref', ['payload' => $request->all()]);
            return response()->json(['message' => 'payment_ref missing'], 400);
        }

        try {
            // Interroger Konnect pour obtenir le statut
            $konnectBase = rtrim(config('services.konnect.api_base'), '/');
            $apiKey = config('services.konnect.api_key');

            $resp = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Accept' => 'application/json',
            ])->get("{$konnectBase}/api/v2/payments/{$paymentRef}");

            if ($resp->failed()) {
                Log::error('Konnect webhook: failed to fetch payment', [
                    'payment_ref' => $paymentRef,
                    'status' => $resp->status(),
                    'body' => $resp->body()
                ]);
                return response()->json(['message' => 'Failed to verify payment with Konnect'], 500);
            }

            $data = $resp->json();

            // Extraire le statut
            $status    = $data['payment']['status'] 
                         ?? ($data['payment']['transactions'][0]['status'] ?? null);
            $orderId   = $data['payment']['orderId'] ?? null;
            $konnectId = $data['payment']['id'] ?? null;

            if (!$status) {
                Log::info('Konnect webhook: status not found', [
                    'payment_ref' => $paymentRef,
                    'payload' => $data
                ]);
                return response()->json(['message' => 'Status non final: null'], 200);
            }

            // Rechercher le paiement local
            $payment = Payment::where('provider_payment_id', $paymentRef)
                        ->orWhere('provider_payment_id', $orderId)
                        ->orWhere('provider_payment_id', $konnectId)
                        ->first();

            if (!$payment) {
                Log::info('Konnect webhook: local payment not found', [
                    'payment_ref' => $paymentRef,
                    'payload' => $data
                ]);
                return response()->json(['message' => 'Payment local introuvable, logged'], 200);
            }

            // Normaliser le statut
            $statusLower = strtolower($status);

            if (in_array($statusLower, ['success', 'paid', 'completed'])) {
                if ($payment->status !== 'completed') {
                    // Marquer le paiement comme complété
                    $payment->markCompleted();

                    // Déclencher l'event pour générer invoice et activer subscription
                    event(new PaymentCompleted($payment));

                    // Activer la subscription si elle existe
                    if ($payment->subscription && $payment->subscription->status !== 'active') {
                        $payment->subscription->status = 'active';
                        $payment->subscription->save();
                    }

                    Log::info('Konnect webhook: payment completed', ['payment_id' => $payment->id]);
                }
                return response()->json(['message' => 'Payment processed (completed)'], 200);
            }

            if (in_array($statusLower, ['failed', 'error'])) {
                if ($payment->status !== 'failed') {
                    $payment->markFailed();
                    if ($payment->subscription) {
                        $payment->subscription->status = 'cancelled';
                        $payment->subscription->save();
                    }
                    Log::info('Konnect webhook: payment failed', ['payment_id' => $payment->id]);
                }
                return response()->json(['message' => 'Payment processed (failed)'], 200);
            }

            // Statut non final
            Log::info('Konnect webhook: non-final status', [
                'payment_id' => $payment->id,
                'status' => $status,
            ]);

            return response()->json(['message' => 'Status non final: ' . $status], 200);

        } catch (\Throwable $e) {
            Log::error('Konnect webhook: exception', [
                'error' => $e->getMessage(),
                'payment_ref' => $paymentRef,
                'payload' => $request->all()
            ]);
            return response()->json(['message' => 'Server error'], 500);
        }
    }
}
