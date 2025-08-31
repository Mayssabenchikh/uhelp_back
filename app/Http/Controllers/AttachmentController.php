<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class AttachmentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10 MB
            'attachable_type' => 'nullable|string',
            'attachable_id' => 'nullable|integer',
        ]);

        $file = $request->file('file');

        $disk = 'public';
        $folder = 'attachments/' . date('Y/m/d');
        $storedName = Str::random(36) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $storedName, $disk);

        $attachment = Attachment::create([
            'attachable_type' => null,
            'attachable_id' => null,
            'user_id' => Auth::id(),
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        // si front a fourni attachable info, on attache tout de suite (optionnel)
        if ($request->filled('attachable_type') && $request->filled('attachable_id')) {
            $map = [
                'chat_message' => \App\Models\ChatMessage::class,
                'conversation' => \App\Models\Conversation::class,
            ];
            if (isset($map[$request->attachable_type])) {
                $attachment->attachable_type = $map[$request->attachable_type];
                $attachment->attachable_id = $request->attachable_id;
                $attachment->save();
            }
        }

        return response()->json([
            'ok' => true,
            'attachment' => $attachment,
            'url' => $attachment->url()
        ], 201);
    }

    public function destroy(Attachment $attachment)
    {
        $this->authorize('delete', $attachment);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return response()->json(['ok' => true], 200);
    }
    public function show(Attachment $attachment)
{
    // Autorisation (policy)
    $this->authorize('view', $attachment);

    $disk = $attachment->disk ?: 'public';
    $path = $attachment->path;

    if (! Storage::disk($disk)->exists($path)) {
        return response()->json(['message' => 'File not found'], 404);
    }

    // Pour driver 'public' local : obtenir le chemin physique
    if ($disk === 'public') {
        $fullPath = Storage::disk($disk)->path($path);
        return response()->file($fullPath, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline; filename="'.basename($attachment->filename).'"'
        ]);
    }

    // Pour S3 ou autres : stream le fichier (compatible)
    return Storage::disk($disk)->response($path, $attachment->filename, [
        'Content-Disposition' => 'inline; filename="'.$attachment->filename.'"'
    ]);
}
}
