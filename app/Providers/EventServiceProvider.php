<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\PaymentCompleted;
use App\Listeners\ActivateSubscription;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PaymentCompleted::class => [
            ActivateSubscription::class,
        ],
    ];

    public function boot()
    {
        parent::boot();
    }
}
