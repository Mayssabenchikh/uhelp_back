<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{

    use HasApiTokens, Notifiable,Billable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', 

    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
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
 // relation si tu veux garder plusieurs subscriptions locales
    public function subscriptionsLocal()
    {
        return $this->hasMany(Subscription::class);
    }

    // relation vers la subscription "active" la plus récente (helper)
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latestOfMany();
    }

}