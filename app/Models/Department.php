<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name'];

    public function agents()
    {
        return $this->hasMany(User::class)->where('role', 'agent');
    }
}
