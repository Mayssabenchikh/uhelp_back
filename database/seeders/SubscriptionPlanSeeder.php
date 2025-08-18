<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('subscription_plans')->insert([
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'price' => 19.99,
                'billing_cycle' => 'monthly',
                'ticket_limit' => 5,
                'features' => json_encode(['Support par email', '5 tickets par mois']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 49.99,
                'billing_cycle' => 'monthly',
                'ticket_limit' => 20,
                'features' => json_encode(['Support prioritaire', '20 tickets par mois']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 99.99,
                'billing_cycle' => 'monthly',
                'ticket_limit' => null, // illimité
                'features' => json_encode(['Support dédié', 'Tickets illimités', 'Conseiller attitré']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
