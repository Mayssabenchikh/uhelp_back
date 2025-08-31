<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'user_id', 'body', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // relation polymorphique (un message peut avoir plusieurs attachments)
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
