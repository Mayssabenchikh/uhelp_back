<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Storage;
use App\Models\Ticket;
use App\Models\Department;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department_id',
        'profile_photo',
        'phone_number',
        'location', // ajouté
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Pour inclure automatiquement l'URL dans la sérialisation JSON
    protected $appends = ['profile_photo_url'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'agent_id');
    }

    public function internalNotes()
    {
        return $this->hasMany(InternalNote::class, 'user_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(\App\Models\Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }

    // Accessor pour générer l'URL publique de la photo
    public function getProfilePhotoUrlAttribute()
    {
        if (!$this->profile_photo) {
            return null;
        }

        // Storage disk 'public' mappe sur /storage via storage:link
        return Storage::disk('public')->url($this->profile_photo);
    }
   public function createdTickets()
    {
        return $this->hasMany(Ticket::class, 'client_id');
    }

    /**
     * Tickets assignés à cet utilisateur (en tant qu'agent)
     */
    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'agentassigne_id');
    }

    /**
     * Tickets résolus par cet agent (agentassigne_id + statut = closed)
     */
    public function resolvedTickets()
    {
        return $this->hasMany(Ticket::class, 'agentassigne_id')->where('statut', 'closed');
    }
}
