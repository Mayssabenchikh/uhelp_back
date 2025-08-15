<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = ['agent_id', 'day_of_week', 'start_time', 'end_time'];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
