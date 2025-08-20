<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{

    use HasApiTokens, Notifiable,HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', 
        'department_id',

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
public function subscriptions()
{
    return $this->hasMany(\App\Models\Subscription::class);
}

public function payments()
{
    return $this->hasMany(\App\Models\Payment::class);
}


}