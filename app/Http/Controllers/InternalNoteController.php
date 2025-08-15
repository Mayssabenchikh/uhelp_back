<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InternalNote;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // ✅ namespace correct

class InternalNoteController extends Controller
{use AuthorizesRequests;
    // Liste toutes les notes internes d'un ticket
    public function index(Ticket $ticket)
    {
        return response()->json($ticket->internalNotes()->with('author:id,name,email')->get());
    }

    // Créer une note interne
    public function store(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $note = $ticket->internalNotes()->create([
            'user_id' => Auth::id(),
            'message' => $validated['message'],
        ]);

        return response()->json([
            'message' => 'Internal note created',
            'note' => $note->load('author:id,name,email')
        ], 201);
    }

    // Afficher une note interne
    public function show(InternalNote $internalNote)
    {
        return response()->json($internalNote->load('author:id,name,email', 'ticket:id,titre'));
    }

    // Mettre à jour une note interne
    public function update(Request $request, InternalNote $internalNote)
    {
        $this->authorize('update', $internalNote); // optionnel : vérifier que l'auteur ou admin modifie

        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $internalNote->update($validated);

        return response()->json([
            'message' => 'Internal note updated',
            'note' => $internalNote
        ]);
    }

    // Supprimer une note interne
    public function destroy(InternalNote $internalNote)
    {
        $this->authorize('delete', $internalNote); // optionnel

        $internalNote->delete();

        return response()->json(['message' => 'Internal note deleted']);
    }
}
