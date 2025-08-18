<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use Carbon\Carbon;

class ActivateSubscription
{
    public function handle(PaymentCompleted $event)
    {
        $payment = $event->payment->fresh();
        $subscription = $payment->subscription;
        if (!$subscription) return;

        $plan = $subscription->plan;
        $now = Carbon::now();

        if ($plan) {
            $months = $plan->billing_cycle === 'yearly' ? 12 : ($plan->billing_cycle === 'one_time' ? 0 : 1);
            $ends = $months === 0 ? null : $now->copy()->addMonths($months);
            $subscription->markActive($now, $ends, $payment->provider_payment_id);
        } else {
            $subscription->markActive($now, $now->copy()->addMonth(), $payment->provider_payment_id);
        }
    }
}
