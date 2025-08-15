<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Carbon\Carbon;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Expire subscriptions whose ends_at is past';

    public function handle(): int
    {
        $now = Carbon::now();
        $expired = Subscription::whereNotNull('ends_at')->where('ends_at', '<', $now)->where('status', '!=', 'expired')->get();

        foreach ($expired as $sub) {
            $sub->update(['status' => 'expired']);
            // TODO: notifier l'utilisateur (mail / notification)
            $this->info('Expired subscription: ' . $sub->id);
        }

        $this->info('Done');
        return Command::SUCCESS;
    }
}
