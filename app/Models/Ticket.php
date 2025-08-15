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
    public function responses()
{
    return $this->hasMany(\App\Models\TicketResponse::class);
}
public function internalNotes()
{
    return $this->hasMany(InternalNote::class);
}
}