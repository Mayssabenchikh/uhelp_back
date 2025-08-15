<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Event;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret') ?? env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response()->json(['message' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Traitement selon event type
        switch ($event->type) {
            case 'invoice.payment_succeeded':
                $invoice = $event->data->object;
                $customerId = $invoice->customer;
                // trouver user par stripe customer id (Cashier stocke stripe_id dans users)
                $user = \App\Models\User::where('stripe_id', $customerId)->first();
                if ($user) {
                    // créer paiement local
                    Payment::create([
                        'user_id' => $user->id,
                        'subscription_id' => Subscription::where('stripe_subscription_id', $invoice->subscription)->first()?->id,
                        'stripe_payment_id' => $invoice->payment_intent ?? $invoice->id,
                        'amount' => ($invoice->amount_paid / 100.0),
                        'currency' => $invoice->currency,
                        'status' => 'succeeded',
                        'meta' => $invoice->toArray(),
                    ]);

                    // mettre subscription active
                    if ($s = Subscription::where('stripe_subscription_id', $invoice->subscription)->first()) {
                        $s->update(['status' => 'active']);
                    }
                }
                break;

            case 'invoice.payment_failed':
                $invoice = $event->data->object;
                if ($s = Subscription::where('stripe_subscription_id', $invoice->subscription)->first()) {
                    $s->update(['status' => 'past_due']);
                }
                break;

            case 'customer.subscription.deleted':
            case 'customer.subscription.updated':
                $stripeSub = $event->data->object;
                if ($s = Subscription::where('stripe_subscription_id', $stripeSub->id)->first()) {
                    $s->update([
                        'status' => $stripeSub->status,
                        'starts_at' => isset($stripeSub->current_period_start) ? \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_start) : $s->starts_at,
                        'ends_at' => isset($stripeSub->current_period_end) ? \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end) : $s->ends_at,
                        'meta' => $stripeSub->toArray(),
                    ]);
                }
                break;
        }

        return response()->json(['received' => true]);
    }
}
