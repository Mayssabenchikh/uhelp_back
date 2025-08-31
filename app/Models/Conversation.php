<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['title','type'];

    public function participants()
    {
        return $this->belongsToMany(User::class, 'conversation_user')->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

   public function groups()
{
    return $this->belongsToMany(Group::class, 'conversation_group', 'conversation_id', 'group_id')
                ->withTimestamps();
}


    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

 
    public function hasParticipant($userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

   
    public function attachments()
{
    return $this->morphMany(\App\Models\Attachment::class, 'attachable');
}


}
