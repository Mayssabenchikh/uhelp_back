<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KonnectWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $apiBase = env('KONNECT_API_BASE', 'https://api.sandbox.konnect.network/api/v2');
        $apiKey  = env('KONNECT_API_KEY');

        // 1) PRIORITÉ : payment_ref en GET (callback simple)
        $paymentRef = $request->query('payment_ref');

        // 2) Si POST JSON et contient un id/ref, l'utiliser
        if (!$paymentRef && $request->isMethod('post')) {
            $json = json_decode($request->getContent(), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $paymentRef = $json['data']['id'] ?? $json['data']['payment_ref'] ?? $json['payment_ref'] ?? null;
            }
        }

        if (empty($paymentRef)) {
            Log::warning('Konnect webhook: no payment_ref', ['query' => $request->query(), 'body_len' => strlen($request->getContent())]);
            return response()->json(['message' => 'Missing payment_ref'], 400);
        }

        // 3) Vérifier côté Konnect pour confirmer le statut (server->server)
        try {
            $resp = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Accept'    => 'application/json',
            ])->get("{$apiBase}/payments/{$paymentRef}");

            if ($resp->failed()) {
                Log::error('Konnect API error', ['payment_ref' => $paymentRef, 'status' => $resp->status(), 'body' => $resp->body()]);
                return response('Konnect API error', 500);
            }

            $payload = $resp->json();
            $data = $payload['data'] ?? $payload;

            $providerId = $data['id'] ?? $data['provider_payment_id'] ?? $paymentRef;
            $status = $data['status'] ?? null;
            $amount = $data['amount'] ?? null;
            $currency = $data['currency'] ?? 'TND';

            // Idempotent create/update
            $payment = Payment::firstOrNew(['provider_payment_id' => $providerId]);
            $payment->amount = $amount ?? $payment->amount ?? 0;
            $payment->currency = $currency;
            $payment->status = $status ?? $payment->status ?? 'unknown';
            $payment->save();

            // Exemple : activer subscription si nécessaire
            if ($payment->subscription_id && in_array($payment->status, ['success','completed','succeeded'])) {
                $sub = Subscription::find($payment->subscription_id);
                if ($sub) {
                    $sub->status = 'active';
                    $sub->save();
                }
            }

            Log::info('Konnect callback processed', ['payment_ref' => $paymentRef, 'status' => $payment->status]);
            return response()->json(['message' => 'ok'], 200);

        } catch (\Throwable $e) {
            Log::error('Exception checking Konnect payment', ['err' => $e->getMessage()]);
            return response('Error', 500);
        }
    }
}
