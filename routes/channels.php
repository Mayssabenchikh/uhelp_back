<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;

/*
| Register the event broadcasting authorization routes.
| We protect them with auth:sanctum so Echo can call /broadcasting/auth
*/
Broadcast::routes(['middleware' => ['auth:sanctum']]);

/*
| Private channel for a conversation between participants (client/agent)
| and admins.
*/
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    if (! $conversation) return false;

    // hasParticipant should exist on Conversation model
    if (method_exists($conversation, 'hasParticipant') && $conversation->hasParticipant($user->id)) {
        return ['id' => $user->id, 'name' => $user->name, 'role' => $user->role];
    }

    // allow admin role to monitor/join if needed
    if (($user->role ?? null) === 'admin') {
        return ['id' => $user->id, 'name' => $user->name, 'role' => 'admin'];
    }

    return false;
});
