<?php
namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChatMessage $message;

    public function __construct(ChatMessage $message)
    {
        $this->message = $message->load(['user','attachments']);
    }

    public function broadcastOn()
    {
        return new PrivateChannel('conversation.' . $this->message->conversation_id);
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'user_id' => $this->message->user_id,
            'body' => $this->message->body,
            'meta' => $this->message->meta,
            'created_at' => $this->message->created_at->toDateTimeString(),
            'attachments' => $this->message->attachments->map(fn($a)=>[
                'id'=>$a->id,'url'=>$a->url(),'filename'=>$a->filename,'mime'=>$a->mime,'size'=>$a->size
            ])->toArray(),
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'role' => $this->message->user->role,
                'profile_photo' => $this->message->user->profile_photo
            ]
        ];
    }
}
