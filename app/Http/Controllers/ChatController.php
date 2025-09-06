<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Group;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChatController extends Controller
{
     public function index(Request $request)
    {
        try {
            $perPage = max(1, (int)$request->get('per_page', 20));
            $status = $request->get('status', null);
            $search = $request->get('search', null);

            // Si la table n'existe pas, retourner un payload vide pour éviter 500
            if (! Schema::hasTable('conversations')) {
                Log::warning('ChatController@index: table conversations not found');
                return response()->json([
                    'data' => [],
                    'meta' => [
                        'total' => 0,
                        'per_page' => $perPage,
                        'current_page' => 1,
                        'last_page' => 0
                    ],
                    'warning' => 'Table conversations introuvable (dev)'
                ], 200);
            }

            $qb = DB::table('conversations')->select('id', 'title', 'status', 'priority', 'updated_at', 'created_at');

            if ($status) {
                $qb->where('status', $status);
            }

            if ($search !== null && $search !== '') {
                $qb->where(function($q) use ($search) {
                    $q->where('title', 'like', '%'.$search.'%')
                      ->orWhere('id', (int)$search);
                });
            }

            $qb->orderBy('updated_at', 'desc');

            $page = max(1, (int) $request->get('page', 1));
            $items = $qb->forPage($page, $perPage)->get();
            // Recalc total with same filters
            $countQ = DB::table('conversations');
            if ($status) $countQ->where('status', $status);
            if ($search !== null && $search !== '') $countQ->where('title', 'like', '%'.$search.'%');
            $total = (int) $countQ->count();

            $result = [
                'data' => $items,
                'meta' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / max(1, $perPage))
                ]
            ];

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            Log::error('ChatController@index exception: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            // Ne pas exposer la stack en prod — mais en dev on renvoie un message clair
            $message = env('APP_DEBUG', false) ? $e->getMessage() : 'Erreur serveur';
            return response()->json(['message' => $message], 500);
        }
    }
  public function send(Request $request)
{
    $request->validate([
        'conversation_id' => 'required|integer|exists:conversations,id',
        'body' => 'nullable|string',
        'attachment_ids' => 'nullable|array',
        'attachment_ids.*' => 'integer|exists:attachments,id',
        'files' => 'nullable',
    ]);

    $user = Auth::user();
    $conversation = Conversation::findOrFail($request->conversation_id);

    if (! $conversation->hasParticipant($user->id)) {
        return response()->json(['message' => 'Unauthorized to post in this conversation.'], 403);
    }

    try {
        \DB::beginTransaction();

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => $request->body,
            'meta' => $request->input('meta', null),
        ]);

        // claim pre-uploaded attachments
        if ($request->filled('attachment_ids')) {
            \App\Models\Attachment::whereIn('id', $request->attachment_ids)
                ->whereNull('attachable_id')
                ->update([
                    'attachable_type' => ChatMessage::class,
                    'attachable_id' => $message->id,
                ]);
        }

        // process uploaded files inline (same implementation que toi)
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                if (!$file->isValid()) continue;
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
        }

        \DB::commit();

        // recharger relations
        $message->load('attachments','user');

        // broadcast event (utilise l'Event ChatMessageSent)
        event(new \App\Events\ChatMessageSent($message));

        return response()->json(['ok' => true, 'message' => $message], 201);
    } catch (\Throwable $e) {
        \DB::rollBack();
        \Log::error('Chat send error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['message' => 'Erreur lors de l\'envoi du message.','error'=>$e->getMessage()], 500);
    }
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
        // Garde ton implé existante ; je la laisse telle quelle mais la reprends pour sûreté.
        $messages = ChatMessage::where('conversation_id', $conversationId)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return response()->json($messages);
    }

public function conversationDetails($id)
{
    $conversation = Conversation::with(['participants', 'messages'])->findOrFail($id);

    // Pour simplifier, on prend le premier participant qui n'est pas l'agent
    $customer = $conversation->participants()->where('role', '!=', 'agent')->first();

    $totalChats = $conversation->messages()->count();
    $satisfaction = $conversation->messages()->avg('rating') ?? 0; // si vous avez un champ rating
    $joinedAt = optional($customer)->created_at?->format('Y-m-d') ?? null;

    return response()->json([
        'customer' => [
            'id' => $customer->id ?? null,
            'name' => $customer->name ?? 'Anonymous',
            'email' => $customer->email ?? '',
            'avatar' => $customer->avatar ?? null,
            'location' => $customer->location ?? '', // si champ disponible
            'joined_at' => $joinedAt,
        ],
        'chat_statistics' => [
            'total_chats' => $totalChats,
            'satisfaction' => round($satisfaction, 1),
        ],
        'actions' => [
            'can_create_ticket' => true,
            'can_view_history' => true,
            'can_report_issue' => true,
        ],
        'conversation' => [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'status' => $conversation->status,
            'priority' => $conversation->priority,
        ]
    ]);
}

}
