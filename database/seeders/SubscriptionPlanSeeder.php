<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::create([
            'name' => 'Free',
            'stripe_price_id' => null,
            'ticket_limit' => 3,
            'interval' => 'month',
            'amount' => 0,
            'description' => 'Plan gratuit — 3 tickets / mois',
        ]);

        SubscriptionPlan::create([
            'name' => 'Pro',
            'stripe_price_id' => 'price_pro_sample',
            'ticket_limit' => 50,
            'interval' => 'month',
            'amount' => 29.99,
            'description' => 'Pro — 50 tickets / mois',
        ]);

        SubscriptionPlan::create([
            'name' => 'Enterprise',
            'stripe_price_id' => 'price_enterprise_sample',
            'ticket_limit' => 200,
            'interval' => 'month',
            'amount' => 199.99,
            'description' => 'Enterprise — 200 tickets / mois',
        ]);
    }
}
