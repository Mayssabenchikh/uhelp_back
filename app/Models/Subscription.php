<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'current_period_started_at',
        'current_period_ends_at',
        'tickets_used',
        'provider_subscription_id',
    ];

    protected $casts = [
        'current_period_started_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'tickets_used' => 'integer',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticketsRemaining(): ?int
    {
        $limit = $this->plan->ticket_limit;
        if (is_null($limit)) {
            return null; // unlimited
        }
        return max(0, $limit - $this->tickets_used);
    }
}
