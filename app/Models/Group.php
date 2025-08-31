<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['name','description','owner_id'];

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class)->withPivot('role')->withTimestamps();
    }

    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

  public function conversations()
{
    return $this->belongsToMany(Conversation::class, 'conversation_group', 'group_id', 'conversation_id')
                ->withTimestamps();
}

}
