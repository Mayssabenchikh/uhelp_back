<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Models\Group;
class ChatController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = max(1, (int)$request->get('per_page', 20));
            $status = $request->get('status', null);
            $search = $request->get('search', null);

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
            $message = env('APP_DEBUG', false) ? $e->getMessage() : 'Erreur serveur';
            return response()->json(['message' => $message], 500);
        }
    }

public function send(Request $request)
{
    $validated = $request->validate([
        'conversation_id' => 'required|integer|exists:conversations,id',
        'body' => 'nullable|string',
        'attachment_ids' => 'nullable|array',
        'attachment_ids.*' => 'integer|exists:attachments,id',
        'files' => 'nullable',
        'files.*' => 'file|max:51200',
        'meta' => 'nullable|array',
    ]);

    $user = $request->user();
    if (! $user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    $conversation = Conversation::findOrFail($validated['conversation_id']);

    if (! $conversation->hasParticipant($user->id)) {
        return response()->json(['message' => 'Unauthorized to post in this conversation.'], 403);
    }

    Log::debug('ChatController@send incoming', [
        'conversation_id' => $validated['conversation_id'],
        'has_body' => isset($validated['body']) && trim((string)$validated['body']) !== '',
        'has_files' => $request->hasFile('files'),
        'files_count' => $request->hasFile('files') ? count($request->file('files')) : 0,
        'attachment_ids' => $validated['attachment_ids'] ?? null,
        'user_id' => $user->id,
    ]);

    try {
        /** @var \App\Models\ChatMessage $message */
        $message = DB::transaction(function () use ($validated, $request, $conversation, $user) {

            $body = (string) ($validated['body'] ?? '');
            if (trim($body) === '' && ($request->hasFile('files') || !empty($validated['attachment_ids']))) {
                // Try to get the filename from the first uploaded file
                $filename = null;
                
                if ($request->hasFile('files')) {
                    $firstFile = $request->file('files')[0] ?? null;
                    if ($firstFile && $firstFile->isValid()) {
                        $filename = $firstFile->getClientOriginalName();
                    }
                } elseif (!empty($validated['attachment_ids'])) {
                    $firstAttachment = \App\Models\Attachment::whereIn('id', $validated['attachment_ids'])
                        ->where('user_id', $user->id)
                        ->first();
                    if ($firstAttachment) {
                        $filename = $firstAttachment->filename;
                    }
                }
                
                // Use filename or fallback to generic message
                if ($filename) {
                    $body = $filename;
                } else {
                    $body = '[Fichier sans nom]';
                }
            }

            $message = new ChatMessage();
            $message->conversation_id = $conversation->id;
            $message->user_id = $user->id;
            $message->body = $body;
            $message->meta = $validated['meta'] ?? null;
            $message->save();

            // Attach pre-uploaded attachments (only those belonging to current user)
            if (!empty($validated['attachment_ids'])) {
                \App\Models\Attachment::whereIn('id', $validated['attachment_ids'])
                    ->whereNull('attachable_id')
                    ->where('user_id', $user->id)
                    ->update([
                        'attachable_type' => ChatMessage::class,
                        'attachable_id' => $message->id,
                    ]);
            }

            // Handle uploaded files
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    if (! $file->isValid()) continue;

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

            return $message;
        }, 5); // <- DB::transaction returns the closure result

        // Now the analyzer knows $message is a ChatMessage object
        $message->load('attachments', 'user');

        Log::info('Chat message created', ['id' => $message->id, 'conversation' => $message->conversation_id, 'user' => $user->id]);

        try {
            event(new ChatMessageSent($message));
        } catch (\Throwable $e) {
            Log::error('Broadcast error (non-fatal): '.$e->getMessage());
        }

        return response()->json(['ok' => true, 'message' => $message], 201);
    } catch (\Throwable $e) {
        Log::error('Chat send error: '.$e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'user_id' => $user->id ?? null,
            'conversation_id' => $validated['conversation_id'] ?? null
        ]);
        return response()->json(['message' => 'Erreur lors de l\'envoi du message.', 'error' => $e->getMessage()], 500);
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

        $conversation->participants()->attach($request->participants);

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
            ->with(['user', 'attachments'])
            ->orderBy('created_at')
            ->get();

        // add public url to attachments for each message
        $messages->each(function($m) {
            if ($m->attachments && $m->attachments->count() > 0) {
                $m->attachments->each(function($a) {
                    try {
                        $a->url = Storage::disk($a->disk ?? 'public')->url($a->path);
                    } catch (\Throwable $e) {
                        $a->url = url('/storage/' . ltrim($a->path, '/'));
                    }
                });
            }
        });

        return response()->json($messages);
    }

    public function conversationDetails($id)
    {
        $conversation = Conversation::with(['participants', 'groups.users'])->findOrFail($id);

        $members = collect();

        foreach ($conversation->participants as $u) {
            $members->push($u);
        }

        foreach ($conversation->groups as $group) {
            foreach ($group->users as $u) {
                $members->push($u);
            }
        }

        $members = $members->unique('id')->values();

        $authId = Auth::id();

        $mapped = $members->map(function($u) use ($authId) {
            $pivotRole = null;
            if (isset($u->pivot) && isset($u->pivot->role)) {
                $pivotRole = $u->pivot->role;
            }
            return [
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar ?? null,
                'role' => $pivotRole ?? ($u->role ?? null),
                'online' => Cache::has("user_online_{$u->id}"),
                'is_member' => true,
            ];
        });

        $isMember = $mapped->contains('id', $authId);

        $response = [
            'id' => $conversation->id,
            'customer' => [
                'name' => $conversation->title ?? $conversation->customer_name ?? 'Unknown',
                'email' => $conversation->email ?? null,
                'location' => $conversation->location ?? null,
                'joined' => optional($conversation->created_at)->toDateString(),
            ],
            'members' => $mapped,
            'is_member' => $isMember,
            'stats' => [
                'totalChats' => $conversation->messages()->count(),
                'satisfaction' => method_exists($conversation, 'feedbacks') ? $conversation->feedbacks()->avg('rating') ?? 0 : 0
            ],
        ];

        return response()->json($response);
    }

    public function join(Request $request, $id)
    {
        $conversation = Conversation::findOrFail($id);
        $user = $request->user();

        $exists = $conversation->participants()->where('users.id', $user->id)->exists();
        if ($exists) {
            return response()->json(['message' => 'Already a member', 'is_member' => true]);
        }

        $role = $request->input('role', 'member');
        $conversation->participants()->attach($user->id, ['role' => $role]);

        return response()->json(['message' => 'Joined conversation', 'is_member' => true, 'user_id' => $user->id]);
    }

    public function storeDirect(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'other_user_id' => 'required|integer|exists:users,id',
            'title' => 'nullable|string|max:255',
            'message' => 'nullable|string'
        ]);

        $otherId = (int) $request->input('other_user_id');

        if ($otherId === $user->id) {
            return response()->json(['message' => 'Cannot create conversation with yourself'], 422);
        }

        $ids = [$user->id, $otherId];

        $existing = Conversation::whereHas('participants', function ($q) use ($ids) {
                $q->where('user_id', $ids[0]);
            })
            ->whereHas('participants', function ($q) use ($ids) {
                $q->where('user_id', $ids[1]);
            })
            ->withCount('participants')
            ->having('participants_count', 2)
            ->first();

        if ($existing) {
            $existing->load(['participants', 'messages' => function($q){
                $q->latest()->limit(50);
            }]);
            return response()->json(['conversation' => $existing, 'created' => false], 200);
        }

        DB::beginTransaction();
        try {
            $conversation = Conversation::create([
                'title' => $request->input('title') ?? null,
                'type' => 'private',
                'status' => 'Active',
            ]);

            $conversation->participants()->attach($ids);

            if ($request->filled('message')) {
                $message = ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'body' => $request->input('message'),
                ]);

                $message->load('user');

                try {
                    event(new ChatMessageSent($message));
                } catch (\Throwable $e) {
                    Log::error('Broadcast error (non-fatal): ' . $e->getMessage());
                }
            }

            DB::commit();

            $conversation->load(['participants', 'messages' => function($q){
                $q->latest()->limit(50);
            }]);

            return response()->json(['conversation' => $conversation, 'created' => true], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('storeDirect error: '.$e->getMessage());
            return response()->json(['message' => 'Could not create conversation', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Download chat attachment securely
     */
    public function downloadAttachment(Request $request, $attachmentId)
    {
        try {
            Log::info('Download attachment request', ['attachment_id' => $attachmentId, 'user_id' => Auth::id()]);

            // Find the attachment using the Attachment model
            $attachment = \App\Models\Attachment::where('id', $attachmentId)
                ->where('attachable_type', 'App\\Models\\ChatMessage')
                ->first();

            if (!$attachment) {
                Log::warning('Attachment not found', ['attachment_id' => $attachmentId]);
                return response()->json(['message' => 'Attachment not found'], 404);
            }

            Log::info('Attachment found', ['attachment' => $attachment->toArray()]);

            // Check if user has access to this attachment via conversation membership
            $chatMessage = ChatMessage::find($attachment->attachable_id);
            if (!$chatMessage) {
                Log::warning('Message not found', ['attachable_id' => $attachment->attachable_id]);
                return response()->json(['message' => 'Message not found'], 404);
            }

            Log::info('Chat message found', ['message_id' => $chatMessage->id, 'conversation_id' => $chatMessage->conversation_id]);

            $conversation = Conversation::find($chatMessage->conversation_id);
            if (!$conversation) {
                Log::warning('Conversation not found', ['conversation_id' => $chatMessage->conversation_id]);
                return response()->json(['message' => 'Conversation not found'], 404);
            }

            // Simplified access check - allow if user is the message sender or attachment owner
            $userId = Auth::id();
            $hasAccess = ($chatMessage->user_id == $userId) || ($attachment->user_id == $userId);
            
            // Also check conversation membership
            if (!$hasAccess) {
                $hasAccess = DB::table('conversation_user')
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $userId)
                    ->exists();
            }

            if (!$hasAccess) {
                Log::warning('Access denied', [
                    'user_id' => $userId,
                    'message_user_id' => $chatMessage->user_id,
                    'attachment_user_id' => $attachment->user_id
                ]);
                return response()->json(['message' => 'Access denied'], 403);
            }

            // Check if file exists on the specified disk
            if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
                Log::error('File not found on storage', [
                    'disk' => $attachment->disk,
                    'path' => $attachment->path,
                    'full_path' => Storage::disk($attachment->disk)->path($attachment->path)
                ]);
                return response()->json(['message' => 'File not found on storage'], 404);
            }

            Log::info('Serving file download', ['filename' => $attachment->filename]);

            // Return file as download
            return response()->download(
                Storage::disk($attachment->disk)->path($attachment->path),
                $attachment->filename,
                [
                    'Content-Type' => $attachment->mime,
                    'Content-Disposition' => 'attachment; filename="' . $attachment->filename . '"'
                ]
            );

        } catch (\Exception $e) {
            Log::error('Download attachment error: ' . $e->getMessage(), [
                'attachment_id' => $attachmentId,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Download failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * View chat attachment securely (for images, PDFs, etc.)
     */
    public function viewAttachment(Request $request, $attachmentId)
    {
        try {
            Log::info('View attachment request', ['attachment_id' => $attachmentId]);

            // Check token in query params or headers
            $token = $request->get('token') ?? $request->bearerToken();
            if (!$token) {
                Log::warning('No authentication token provided');
                return response()->json(['message' => 'Authentication required'], 401);
            }

            // Authenticate user with token
            $user = null;
            if ($request->bearerToken()) {
                // Token in Authorization header
                $user = Auth::guard('sanctum')->user();
                Log::info('Using bearer token authentication', ['user_id' => $user?->id]);
            } else {
                // Token in query param - need to manually verify
                Log::info('Using query token authentication', ['token_prefix' => substr($token, 0, 10) . '...']);
                $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($personalAccessToken && !$personalAccessToken->cant('*')) {
                    $user = $personalAccessToken->tokenable;
                    Auth::setUser($user);
                    Log::info('Query token authentication successful', ['user_id' => $user->id]);
                } else {
                    Log::warning('Query token authentication failed', [
                        'token_found' => $personalAccessToken ? 'yes' : 'no',
                        'token_expired' => $personalAccessToken ? $personalAccessToken->cant('*') : 'n/a'
                    ]);
                }
            }

            if (!$user) {
                Log::warning('Invalid authentication token');
                return response()->json(['message' => 'Invalid authentication token'], 401);
            }

            Log::info('User authenticated', ['user_id' => $user->id]);

            // Find the attachment using the Attachment model
            $attachment = \App\Models\Attachment::where('id', $attachmentId)
                ->where('attachable_type', 'App\\Models\\ChatMessage')
                ->first();

            if (!$attachment) {
                Log::warning('Attachment not found', ['attachment_id' => $attachmentId]);
                return response()->json(['message' => 'Attachment not found'], 404);
            }

            Log::info('Attachment found', ['attachment' => $attachment->toArray()]);

            // Check if user has access to this attachment via conversation membership
            $chatMessage = ChatMessage::find($attachment->attachable_id);
            if (!$chatMessage) {
                Log::warning('Message not found', ['attachable_id' => $attachment->attachable_id]);
                return response()->json(['message' => 'Message not found'], 404);
            }

            $conversation = Conversation::find($chatMessage->conversation_id);
            if (!$conversation) {
                Log::warning('Conversation not found', ['conversation_id' => $chatMessage->conversation_id]);
                return response()->json(['message' => 'Conversation not found'], 404);
            }

            // Simplified access check - allow if user is the message sender or attachment owner
            $userId = $user->id;
            $hasAccess = ($chatMessage->user_id == $userId) || ($attachment->user_id == $userId);
            
            // Also check conversation membership
            if (!$hasAccess) {
                $hasAccess = DB::table('conversation_user')
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $userId)
                    ->exists();
            }

            if (!$hasAccess) {
                Log::warning('Access denied', [
                    'user_id' => $userId,
                    'message_user_id' => $chatMessage->user_id,
                    'attachment_user_id' => $attachment->user_id
                ]);
                return response()->json(['message' => 'Access denied'], 403);
            }

            // Check if file exists on the specified disk
            if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
                Log::error('File not found on storage', [
                    'disk' => $attachment->disk,
                    'path' => $attachment->path,
                    'full_path' => Storage::disk($attachment->disk)->path($attachment->path)
                ]);
                return response()->json(['message' => 'File not found on storage'], 404);
            }

            Log::info('Serving file for viewing', ['filename' => $attachment->filename]);

            // Return file content for viewing
            return response()->file(
                Storage::disk($attachment->disk)->path($attachment->path),
                [
                    'Content-Type' => $attachment->mime,
                    'Content-Disposition' => 'inline; filename="' . $attachment->filename . '"',
                    'Cache-Control' => 'private, max-age=3600'
                ]
            );

        } catch (\Exception $e) {
            Log::error('View attachment error: ' . $e->getMessage(), [
                'attachment_id' => $attachmentId,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'View failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get attachment info for debugging
     */
    public function attachmentInfo(Request $request, $attachmentId)
    {
        try {
            $attachment = \App\Models\Attachment::where('id', $attachmentId)
                ->where('attachable_type', 'App\\Models\\ChatMessage')
                ->first();

            if (!$attachment) {
                return response()->json(['message' => 'Attachment not found'], 404);
            }

            return response()->json([
                'attachment' => $attachment,
                'file_exists' => Storage::disk($attachment->disk)->exists($attachment->path),
                'disk' => $attachment->disk,
                'path' => $attachment->path,
                'full_path' => Storage::disk($attachment->disk)->path($attachment->path),
                'url' => $attachment->url(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
