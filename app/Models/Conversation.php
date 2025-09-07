<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['title','type'];

    // primary relation name used across the app
    public function participants()
    {
        return $this->belongsToMany(\App\Models\User::class, 'conversation_user')
                    ->withPivot('role') // if you store role on pivot
                    ->withTimestamps();
    }

    // Backwards-compatible alias: some controllers / code expect "users"
    public function users()
    {
        return $this->participants();
    }

    public function messages()
    {
        return $this->hasMany(\App\Models\ChatMessage::class);
    }

    public function groups()
    {
        return $this->belongsToMany(\App\Models\Group::class, 'conversation_group', 'conversation_id', 'group_id')
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
