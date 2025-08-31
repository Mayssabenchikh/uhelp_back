<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Group;

class ChatController extends Controller
{
   public function send(Request $request)
{
    $request->validate([
        'conversation_id' => 'required|integer|exists:conversations,id',
        'body' => 'nullable|string',
        'attachment_ids' => 'nullable|array',
        'attachment_ids.*' => 'integer|exists:attachments,id',
        // accept both 'files' and 'attachments' keys in form-data to be flexible with Postman
        'files' => 'nullable',
        'attachments' => 'nullable',
    ]);

    $user = Auth::user();
    $conversation = Conversation::findOrFail($request->conversation_id);

    if (! $conversation->hasParticipant($user->id)) {
        return response()->json(['message' => 'Unauthorized to post in this conversation.'], 403);
    }

    $message = ChatMessage::create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'body' => $request->body,
        'meta' => $request->input('meta', null),
    ]);

    // 1) Claim attachment_ids (pré-uploadés via AttachmentController)
    if ($request->filled('attachment_ids')) {
        \App\Models\Attachment::whereIn('id', $request->attachment_ids)
            ->whereNull('attachable_id')
            ->update([
                'attachable_type' => ChatMessage::class,
                'attachable_id' => $message->id,
            ]);
    }

    // Helper pour traiter un ou plusieurs fichiers envoyés dans la même requête
    $processUploadFiles = function ($files) use ($message, $user) {
        if (! $files) return;
        // si $files est un UploadedFile unique, le transformer en array
        if (! is_array($files) && ! $files instanceof \Illuminate\Support\Collection) {
            $files = [$files];
        }
        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) continue;
            $disk = 'public';
            $folder = 'attachments/' . date('Y/m/d');
            $storedName = \Illuminate\Support\Str::random(36) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($folder, $storedName, $disk);

            $message->attachments()->create([
                'user_id' => $user->id,
                'disk' => $disk,
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    };

    // 2) Fichiers sous la clé 'files' (form-data: files[] multiple)
    if ($request->hasFile('files')) {
        $processUploadFiles($request->file('files'));
    }

    // 3) Fichiers sous la clé 'attachments' (compatibilité front/back)
    if ($request->hasFile('attachments')) {
        $processUploadFiles($request->file('attachments'));
    }

    // recharger les attachments et user pour response
    $message->load('attachments','user');

    $payload = [
        'id' => $message->id,
        'conversation_id' => $message->conversation_id,
        'user_id' => $message->user_id,
        'body' => $message->body,
        'meta' => $message->meta,
        'created_at' => $message->created_at->toDateTimeString(),
        'attachments' => $message->attachments->map(function ($a) {
            return [
                'id' => $a->id,
                'url' => $a->url(),
                'filename' => $a->filename,
                'mime' => $a->mime,
                'size' => $a->size,
            ];
        })->toArray(),
    ];

    // broadcast (ton event ChatMessageSent acceptant un array payload)
    broadcast(new ChatMessageSent($payload))->toOthers();

    return response()->json(['ok' => true, 'message' => $payload], 201);
}


public function createConversation(Request $request)
{
    $request->validate([
        'title' => 'nullable|string|max:255',
        'participants' => 'required|array|min:1',
        'participants.*' => 'exists:users,id',
        'type' => 'nullable|in:private,group',
        'group_id' => 'nullable|integer|exists:groups,id'
    ]);

    $type = $request->input('type', count($request->participants) > 1 ? 'group' : 'private');

    $conversation = Conversation::create([
        'title' => $request->title,
        'type' => $type,
    ]);

    // attach participants to conversation_user pivot
    $conversation->participants()->attach($request->participants);

    // if group_id provided (Option B), link conversation to group
    if ($request->filled('group_id')) {
        $group = Group::find($request->group_id);
        if ($group) {
            $conversation->groups()->attach($group->id);
        }
    }

    return response()->json([
        'ok' => true,
        'conversation' => $conversation->load('participants','groups'),
    ], 201);
}
    public function getMessages($conversationId)
{
    $messages = ChatMessage::where('conversation_id', $conversationId)
        ->with('user') // si relation user définie
        ->orderBy('created_at')
        ->get();

    return response()->json($messages);
}



}
