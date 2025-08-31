<?php
namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttachmentPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Attachment $attachment)
    {
        // autorise si user est l'uploadeur ou participant de la conversation/message lié
        if ($attachment->user_id === $user->id) return true;

        $attachable = $attachment->attachable;
        if ($attachable instanceof \App\Models\ChatMessage) {
            return $attachable->conversation->participants()->where('user_id', $user->id)->exists();
        }
        if ($attachable instanceof \App\Models\Conversation) {
            return $attachable->participants()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    public function delete(User $user, Attachment $attachment)
    {
        // uploader peut supprimer, ou owner/admin role (si group based)
        return $attachment->user_id === $user->id;
    }
}
