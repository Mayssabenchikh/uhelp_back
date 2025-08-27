<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    public function store(Request $request, $ticketId)
    {
        $ticket = Ticket::findOrFail($ticketId);

        // Vérifie que le ticket est fermé
    if ($ticket->statut !== 'closed') {
            return response()->json([
                'message' => 'Vous ne pouvez laisser un feedback que sur un ticket fermé.'
            ], 403);
        }

        // Vérifie que l'utilisateur est le propriétaire du ticket
        if ($ticket->client_id !== Auth::id()) {
            return response()->json([
                'message' => 'Accès non autorisé.'
            ], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $feedback = Feedback::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Merci pour votre feedback.',
            'feedback' => $feedback
        ], 201);
    }

    public function show($ticketId)
    {
        $feedback = Feedback::where('ticket_id', $ticketId)->first();

        if (!$feedback) {
            return response()->json([
                'message' => 'Aucun feedback trouvé pour ce ticket.'
            ], 404);
        }

        return response()->json($feedback);
    }
}
