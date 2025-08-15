<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use App\Models\Ticket;

/**
 * Class Subscription
 *
 * Représente l'abonnement d'un utilisateur.
 *
 * Colonnes attendues (exemples) :
 *  - id, user_id, subscription_plan_id, stripe_subscription_id, stripe_price_id,
 *    status, starts_at, ends_at, meta (json), created_at, updated_at
 */
class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'stripe_subscription_id',
        'stripe_price_id',
        'status',       // active, past_due, cancelled, expired, trialing
        'starts_at',
        'ends_at',
        'meta',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'meta'      => 'array',
    ];

    /**
     * Relation vers l'utilisateur (client).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation vers le plan associé.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Paiements liés à cet abonnement.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'subscription_id');
    }

    /**
     * Est-ce actif ? (contrôle basique)
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active' && $this->status !== 'trialing') {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }
        return true;
    }

    /**
     * Retourne les bornes temporelles (start, end) de la période courante
     * selon l'interval du plan (month, year, day) ; utile pour le comptage de tickets.
     *
     * @return array [Carbon $periodStart, Carbon $periodEnd]
     */
    public function periodBounds(): array
    {
        $now = Carbon::now();
        $planInterval = $this->plan?->interval ?? 'month';

        return match ($planInterval) {
            'day' => [ $now->copy()->startOfDay(), $now->copy()->endOfDay() ],
            'year' => [ $now->copy()->startOfYear(), $now->copy()->endOfYear() ],
            'month' => [ $now->copy()->startOfMonth(), $now->copy()->endOfMonth() ],
            default => [
                // fallback: depuis starts_at jusqu'à now (utile pour custom intervals)
                $this->starts_at ?? $now->copy()->startOfMonth(),
                $this->ends_at ?? $now,
            ],
        };
    }

    /**
     * Nombre de tickets créés par l'utilisateur pendant la période courante de l'abonnement.
     * Nécessite le modèle Ticket existant avec user_id et created_at.
     *
     * @return int
     */
    public function ticketsCreatedThisPeriod(): int
    {
        if (! $this->user) {
            return 0;
        }
        [$start, $end] = $this->periodBounds();

        return Ticket::where('user_id', $this->user->id)
                     ->whereBetween('created_at', [$start, $end])
                     ->count();
    }

    /**
     * Retourne le nombre de tickets restants pour la période courante (>= 0).
     */
    public function ticketsRemaining(): int
    {
        $planLimit = $this->plan?->ticket_limit ?? 0;
        $used = $this->ticketsCreatedThisPeriod();
        return max(0, $planLimit - $used);
    }
}
