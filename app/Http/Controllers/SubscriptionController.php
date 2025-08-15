<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    // GET /api/subscriptions -> retourne subscription active de l'user
    public function show(Request $request)
    {
        $user = $request->user();
        $sub = $user->activeSubscription?->load('plan');
        return response()->json($sub);
    }

    // POST /api/subscriptions -> create subscription (Cashier + enregistrement local)
    public function store(Request $request)
    {
        $data = $request->validate([
            'price_id' => 'required|string',
            'payment_method' => 'required|string',
        ]);

        $user = $request->user();
        $priceId = $data['price_id'];
        $paymentMethod = $data['payment_method'];

        DB::beginTransaction();
        try {
            // actualise la méthode de paiement par défaut (Cashier)
            $user->updateDefaultPaymentMethod($paymentMethod);

            // crée subscription Stripe via Cashier (nom = 'default')
            $stripeSub = $user->newSubscription('default', $priceId)->create($paymentMethod);

            // lookup plan local
            $plan = SubscriptionPlan::where('stripe_price_id', $priceId)->first();

            $sub = Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan?->id,
                'stripe_subscription_id' => $stripeSub->id,
                'stripe_price_id' => $priceId,
                'status' => 'active',
                'starts_at' => now(),
                'meta' => ['stripe' => $stripeSub->toArray() ?? null],
            ]);

            DB::commit();
            return response()->json(['subscription' => $sub], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur création subscription', 'error' => $e->getMessage()], 500);
        }
    }

    // POST /api/subscriptions/cancel
    public function cancel(Request $request)
    {
        $user = $request->user();
        $sub = $user->activeSubscription;
        if (! $sub) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        try {
            // cancel on stripe via Cashier
            $user->subscription('default')->cancelNow();

            $sub->update([
                'status' => 'cancelled',
                'ends_at' => now(),
            ]);

            return response()->json(['message' => 'Canceled', 'subscription' => $sub]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur annulation', 'error' => $e->getMessage()], 500);
        }
    }
}
