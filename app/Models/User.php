<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{

    use HasApiTokens, Notifiable;

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

}