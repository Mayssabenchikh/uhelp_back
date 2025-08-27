<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreTicketResponseRequest;
use App\Http\Requests\UpdateTicketResponseRequest;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Notifications\TicketReplied;
use App\Notifications\TicketStatusChanged;
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
        if ($user->role === 'agent' && $user->department_id !== optional($ticket->agent)->department_id) {
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
        // Vérifie l'accès au ticket (admin, client propriétaire, ou agent assigné)
        $this->ensureCanAccessTicket($ticket);

        $data = $request->validated();
        $user = Auth::user();

        // On met tout dans une transaction pour garantir l'atomicité
        DB::beginTransaction();

        try {
            // Créer la réponse
            $response = $ticket->responses()->create([
                'ticket_id' => $ticket->id,
                'user_id'   => $user->id,
                'message'   => $data['message'],
            ]);

            // Déterminer le nouveau statut selon la logique métier
            // LOGIQUE APPLIQUÉE (modifiable) :
            // - si agent ou admin répond => 'in_progress' (l'agent travaille / a répondu)
            // - si client répond => 'open' (ticket rouvert / attente agent)
            $oldStatus = $ticket->statut;
            $newStatus = $oldStatus;

            if (in_array($user->role, ['agent', 'admin'])) {
                $newStatus = 'in_progress';
            } else {
                // role 'client' or other non-agent => reopen
                $newStatus = 'open';
            }

            // Mettre à jour le ticket seulement si différent
            if ($oldStatus !== $newStatus) {
                $ticket->statut = $newStatus;
                $ticket->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            // Log: à adapter selon ton logger
            return response()->json([
                'status'  => false,
                'message' => 'Failed to create response: ' . $e->getMessage()
            ], 500);
        }

        // Charger relations pour retour
        $response->load('author:id,name,email');
        $ticket->refresh()->load(['client:id,name,email', 'agent:id,name,email']);

        // Préparer destinataires de la notification "TicketReplied"
        $recipients = collect([$ticket->client, $ticket->agent])
            ->filter()
            ->reject(fn ($recipient) => $recipient->id === $user->id)
            ->unique('id')
            ->all();

        if (!empty($recipients)) {
            Notification::send($recipients, new TicketReplied($ticket, $response));
        }

        // Si le statut a changé, notifier le client et l'agent du changement de statut
        if ($oldStatus !== $ticket->statut) {
            $statusRecipients = collect([$ticket->client, $ticket->agent])
                ->filter()
                ->reject(fn ($recipient) => $recipient->id === $user->id)
                ->unique('id')
                ->all();

            if (!empty($statusRecipients)) {
                Notification::send($statusRecipients, new TicketStatusChanged($ticket, $oldStatus));
            }
        }

        return response()->json([
            'status'   => true,
            'message'  => 'Response created and ticket status updated',
            'response' => $response,
            'ticket'   => $ticket,
        ], 201);
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
