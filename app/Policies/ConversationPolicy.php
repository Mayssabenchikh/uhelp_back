<?php
namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConversationPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()->where('user_id', $user->id)->exists()
            || $conversation->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $conversation->owner_id === $user->id;
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $conversation->owner_id === $user->id;
    }
}
