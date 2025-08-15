<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Payment
 *
 * Historique des paiements (liés à un user et optionnellement à une subscription).
 *
 * Colonnes attendues (exemples) :
 *  - id, user_id, subscription_id, stripe_payment_id, amount, currency, status, meta (json), created_at, updated_at
 */
class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'user_id',
        'subscription_id',
        'stripe_payment_id',
        'amount',
        'currency',
        'status',   // succeeded, failed, pending, refunded ...
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta'   => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
