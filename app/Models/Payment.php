<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'amount',
        'currency',
        'status',
        'provider_payment_id',
        'description',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function markPending(): self
    {
        $this->status = 'pending';
        $this->save();
        return $this->fresh();
    }

    public function markCompleted(): self
    {
        if ($this->status === 'completed') {
            return $this->fresh(); // idempotence
        }

        $this->status = 'completed';
        $this->save();
        event(new \App\Events\PaymentCompleted($this));
        return $this->fresh();
    }

    public function markFailed(): self
    {
        $this->status = 'failed';
        $this->save();
        return $this->fresh();
    }
    public function invoice()
{
    return $this->hasOne(\App\Models\Invoice::class, 'payment_id');
}

}
