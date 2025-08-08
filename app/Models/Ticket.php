<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use InvalidArgumentException;

class Ticket extends Model
{
    protected $fillable = [
        'titre',
        'description',
        'statut',
        'client_id',
        'agentassigne_id',
        'priorite',
        'closed_at'
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    // Relation client avec vérification de rôle
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id')
            ->where('role', 'client');
    }

    // Relation agent avec vérification de rôle
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agentassigne_id')
            ->where('role', 'agent');
    }

    // Mutateur pour l'assignation d'agent avec validation de rôle
    public function setAgentassigneIdAttribute($value)
    {
        if ($value && !User::where('id', $value)->where('role', 'agent')->exists()) {
            throw new InvalidArgumentException("L'utilisateur assigné doit être un agent");
        }
        
        $this->attributes['agentassigne_id'] = $value;
    }

    // Mutateur pour l'assignation de client avec validation de rôle
    public function setClientIdAttribute($value)
    {
        if (!User::where('id', $value)->where('role', 'client')->exists()) {
            throw new InvalidArgumentException("L'utilisateur doit être un client");
        }
        
        $this->attributes['client_id'] = $value;
    }
}