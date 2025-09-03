<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use App\Models\User;
use App\Models\Subscription;
use App\Models\InternalNote;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
     use SoftDeletes;

    protected $fillable = [
        'titre',
        'description',
        'statut',
        'client_id',
        'agentassigne_id',
        'priorite',
        'category',        // ajouté
        'closed_at',
        'subscription_id',
    ];
    protected $dates = ['deleted_at'];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    protected static function booted()
    {
        // Lors de la création
        static::creating(function ($ticket) {
            if (($ticket->statut ?? null) === 'closed' && empty($ticket->closed_at)) {
                $ticket->closed_at = now();
            }
        });

        // Lors de la mise à jour
        static::updating(function ($ticket) {
            if ($ticket->isDirty('statut')) {
                $old = $ticket->getOriginal('statut');
                $new = $ticket->statut;

                // Passage -> closed
                if ($old !== 'closed' && $new === 'closed') {
                    $ticket->closed_at = now();
                }

                // Re-ouverture -> clear closed_at
                if ($old === 'closed' && $new !== 'closed') {
                    $ticket->closed_at = null;
                }
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id')
            ->where('role', 'client');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agentassigne_id')
            ->where('role', 'agent');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function responses()
    {
        return $this->hasMany(\App\Models\TicketResponse::class);
    }

    public function internalNotes()
    {
        return $this->hasMany(InternalNote::class);
    }

    // Mutators - vérifient l'existence et le rôle (lève exception si invalide)
    public function setAgentassigneIdAttribute($value)
    {
        if ($value && !User::where('id', $value)->where('role', 'agent')->exists()) {
            throw new InvalidArgumentException("L'utilisateur assigné doit être un agent");
        }

        $this->attributes['agentassigne_id'] = $value;
    }

    public function setClientIdAttribute($value)
    {
        if (!User::where('id', $value)->where('role', 'client')->exists()) {
            throw new InvalidArgumentException("L'utilisateur doit être un client");
        }

        $this->attributes['client_id'] = $value;
    }

    public function feedback()
    {
        return $this->hasOne(\App\Models\Feedback::class);
    }
}
