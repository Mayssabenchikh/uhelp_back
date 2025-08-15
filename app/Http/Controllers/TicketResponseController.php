<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;

use App\Http\Requests\StoreTicketResponseRequest;
use App\Http\Requests\UpdateTicketResponseRequest;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Notifications\TicketReplied;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class TicketResponseController extends Controller
{
    // Vérifie que l'utilisateur peut voir / participer au ticket
       protected function ensureCanAccessTicket(Ticket $ticket): void
    {
        $u = Auth::user(); // ✅
        abort_unless(
            $u && (
                $u->role === 'admin'
                || $u->id === (int) $ticket->client_id
                || $u->id === (int) $ticket->agentassigne_id
            ),
            403,
            'Unauthorized'
        );
    }

    // GET /api/tickets/{ticket}/responses
  public function index(Ticket $ticket)
{
    $user = Auth::user();

    // Vérifie que l'utilisateur appartient au département de l'agent assigné
    if($user->role === 'agent' && $user->department_id !== $ticket->agent->department_id){
        abort(403, 'Unauthorized: ticket not in your department');
    }

    return $ticket->responses()
                  ->with('author:id,name,email')
                  ->latest()
                  ->paginate(20);
}


    // POST /api/tickets/{ticket}/responses
    public function store(StoreTicketResponseRequest $request, Ticket $ticket)
{
    $data = $request->validated();
    $user = Auth::user();

    $response = $ticket->responses()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $user->id,
        'message' => $data['message'],
    ]);

    $response->load('author:id,name,email');

    $recipients = collect([$ticket->client, $ticket->agent])
        ->filter()
        ->reject(fn ($recipient) => $recipient->id === $user->id)
        ->unique('id')
        ->all();

    if (!empty($recipients)) {
        Notification::send($recipients, new TicketReplied($ticket, $response));
    }

    return response()->json($response, 201);
}


    // GET /api/responses/{response}
    public function show(TicketResponse $response)
    {
        $this->ensureCanAccessTicket($response->ticket);
        return $response->load('author:id,name,email');
    }

    // PATCH /api/responses/{response}
    public function update(UpdateTicketResponseRequest $request, TicketResponse $response)
    {
        // authorize() déjà fait dans la FormRequest
        $response->update($request->validated());
        return $response->load('author:id,name,email');
    }

    // DELETE /api/responses/{response}
       public function destroy(Request $request, TicketResponse $response)
    {
        $user = Auth::user(); // ✅
        abort_unless($user && ($user->role === 'admin' || $user->id === (int) $response->user_id), 403);

        $response->delete();
        return response()->json(['deleted' => true]);
    }

}
