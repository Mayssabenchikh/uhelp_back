<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Vérifie les souscriptions actives et passe en past_due celles expirées';

    public function handle()
    {
        $subscriptions = Subscription::where('status','active')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at','<', now())
            ->get();

        $this->info('Found '.$subscriptions->count().' expired subscriptions');
        foreach ($subscriptions as $sub) {
            $sub->markPastDue();
            $this->info("Subscription {$sub->id} marked past_due");
        }
        return 0;
    }
}
