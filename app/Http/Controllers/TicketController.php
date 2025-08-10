<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketStatusChanged;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $tickets = Ticket::with(['client','agent'])->orderBy('created_at','desc')->paginate($perPage);
        return response()->json($tickets);
    }

    public function store(StoreTicketRequest $request)
    {
        $data = $request->validated();
        $ticket = Ticket::create($data);

        if (!empty($ticket->agentassigne_id)) {
            $agent = User::find($ticket->agentassigne_id);
            if ($agent) {
                $agent->notify(new TicketAssigned($ticket, null));
            }
        }

        return response()->json($ticket->load(['client','agent']), 201);
    }

    public function show(Ticket $ticket)
    {
        return response()->json($ticket->load(['client','agent']));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket)
    {
        $validated = $request->validated();

        $oldAgent = $ticket->agentassigne_id;
        $oldStatus = $ticket->statut;

        $ticket->update($validated);

        $newAgent = $ticket->agentassigne_id;
        if ($oldAgent != $newAgent) {
            if ($newAgent) {
                $agent = User::find($newAgent);
                if ($agent) {
                    $agent->notify(new TicketAssigned($ticket, $oldAgent));
                }
            }
            if ($oldAgent && $oldAgent != $newAgent) {
                $old = User::find($oldAgent);
                if ($old) {
                    // personnalise ou crée une notification "Unassigned" si tu veux
                    $old->notify(new \App\Notifications\TicketAssigned($ticket, null));
                }
            }
        }

        $newStatus = $ticket->statut;
        if ($oldStatus !== $newStatus) {
            $client = $ticket->client;
            if ($client) {
                $client->notify(new TicketStatusChanged($ticket, $oldStatus));
            }
            if ($ticket->agentassigne_id) {
                $agent = User::find($ticket->agentassigne_id);
                if ($agent) {
                    $agent->notify(new TicketStatusChanged($ticket, $oldStatus));
                }
            }
        }

        return response()->json($ticket->load(['client','agent']));
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();
        return response()->json(['message' => 'Ticket supprimé'], 200);
    }
}
