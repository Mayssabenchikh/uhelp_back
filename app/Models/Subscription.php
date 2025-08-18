<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
        $limit = $this->plan?->ticket_limit;
        if (is_null($limit)) {
            return null; // illimité
        }
        return max(0, $limit - ($this->tickets_used ?? 0));
    }

    /* ---- status helpers ---- */

    public function markActive(?Carbon $starts = null, ?Carbon $ends = null, ?string $providerId = null): self
    {
        $now = $starts ?? Carbon::now();
        $this->status = 'active';
        $this->current_period_started_at = $now;
        $this->current_period_ends_at = $ends;
        if ($providerId) $this->provider_subscription_id = $providerId;
        $this->save();
        return $this->fresh();
    }

    public function markCancelled(): self
    {
        $this->status = 'cancelled';
        $this->save();
        return $this->fresh();
    }

    public function markExhausted(): self
    {
        $this->status = 'exhausted';
        $this->save();
        return $this->fresh();
    }

    public function markPastDue(): self
    {
        $this->status = 'past_due';
        $this->save();
        return $this->fresh();
    }

    /**
     * Incrémente tickets_used atomiquement.
     * NOTE: cette méthode encapsule sa propre transaction.
     */
    public function incrementTicketsUsed(int $by = 1): self
    {
        return DB::transaction(function () use ($by) {
            $sub = self::lockForUpdate()->find($this->id);
            if (!$sub) {
                throw new \RuntimeException('Subscription introuvable lors de l\'incrémentation');
            }
            $sub->tickets_used = ($sub->tickets_used ?? 0) + $by;
            $sub->save();

            $limit = $sub->plan?->ticket_limit;
            if (!is_null($limit) && $sub->tickets_used >= $limit) {
                $sub->markExhausted();
            }

            return $sub->fresh();
        });
    }

    public function checkAndExpire(): self
    {
        if ($this->status === 'active' && $this->current_period_ends_at !== null && $this->current_period_ends_at->isPast()) {
            $this->markPastDue();
        }
        return $this->fresh();
    }
}
