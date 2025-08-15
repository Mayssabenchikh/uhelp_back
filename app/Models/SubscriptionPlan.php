<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class SubscriptionPlan
 *
 * Représente un plan d'abonnement (catalogue).
 *
 * Colonnes attendues (exemples) :
 *  - id, name, stripe_price_id, stripe_product_id, ticket_limit, interval, amount, description, created_at, updated_at
 */
class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plans';

    protected $fillable = [
        'name',
        'stripe_price_id',
        'stripe_product_id',
        'ticket_limit',
        'interval',       // 'month' | 'year' | 'day' | 'one_time' ...
        'amount',         // montant affiché (decimal)
        'description',
    ];

    protected $casts = [
        'ticket_limit' => 'integer',
        // Laravel supports decimal casting like 'decimal:2'
        'amount' => 'decimal:2',
    ];

    /**
     * Subscriptions souscrites à ce plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscription_plan_id');
    }

    /**
     * Est-ce un plan gratuit ?
     */
    public function isFree(): bool
    {
        return (float) $this->amount <= 0.0;
    }

    /**
     * Scope pour retrouver les plans par interval (utile si tu veux lister 'monthly' par ex.)
     */
    public function scopeInterval($query, string $interval)
    {
        return $query->where('interval', $interval);
    }
}
