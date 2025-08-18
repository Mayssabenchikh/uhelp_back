<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Ticket;
use App\Models\Department;
use App\Models\User;
use App\Models\Subscription;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $tickets = Ticket::with(['client','agent'])->orderBy('created_at','desc')->paginate($perPage);
        return response()->json($tickets);
    }

    /**
     * Crée un ticket en vérifiant la subscription du client et en incrémentant atomiquement tickets_used.
     */
    public function store(StoreTicketRequest $request)
    {
        $data = $request->validated();

        // Si client_id non fourni, prendre l'utilisateur authentifié
        $clientId = $data['client_id'] ?? Auth::id();

        // Vérifier que le client existe et a le rôle 'client'
        $client = User::find($clientId);
        if (!$client || ($client->role ?? null) !== 'client') {
            return response()->json(['message' => 'Client introuvable ou non autorisé'], 422);
        }

        // Récupère la souscription active la plus récente du client
        $subscription = Subscription::where('user_id', $clientId)
            ->where('status', 'active')
            ->where(function($q){
                $q->whereNull('current_period_ends_at')->orWhere('current_period_ends_at', '>', now());
            })
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'Le client n\'a pas de souscription active.'], 403);
        }

        // Vérifie tickets restant (lecture optimiste)
        $remaining = $subscription->ticketsRemaining();
        if (!is_null($remaining) && $remaining <= 0) {
            $subscription->markExhausted();
            return response()->json(['message' => 'Limite de tickets atteinte.'], 422);
        }

        $ticket = null;

        try {
            DB::transaction(function () use ($data, $subscription, $clientId, &$ticket) {
                // lock the subscription row to prevent race conditions
                $sub = Subscription::lockForUpdate()->find($subscription->id);
                if (!$sub) {
                    throw new \RuntimeException('Subscription introuvable pendant la transaction');
                }

                $limit = $sub->plan?->ticket_limit;
                if (!is_null($limit) && ($sub->tickets_used ?? 0) >= $limit) {
                    $sub->markExhausted();
                    throw new \RuntimeException('Limite atteinte');
                }

                $ticket = Ticket::create([
                    'titre' => $data['titre'],
                    'description' => $data['description'],
                    'statut' => $data['statut'] ?? 'open',
                    'client_id' => $clientId,
                    'agentassigne_id' => $data['agentassigne_id'] ?? null,
                    'priorite' => $data['priorite'] ?? null,
                    'subscription_id' => $sub->id,
                ]);

                // incrémenter directement dans la même transaction
                $sub->tickets_used = ($sub->tickets_used ?? 0) + 1;
                $sub->save();

                if (!is_null($limit) && $sub->tickets_used >= $limit) {
                    $sub->markExhausted();
                }
            });
        } catch (\Throwable $e) {
            Log::error('Ticket creation failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création du ticket.', 'error' => $e->getMessage()], 500);
        }

        if ($ticket === null) {
            return response()->json(['message' => 'Erreur lors de la création du ticket.'], 500);
        }

        // Notifications : si ticket a un agent assigné, notifier
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

    public function ticketsByDepartment($departmentId)
    {
        $department = Department::findOrFail($departmentId);

        $tickets = Ticket::whereIn('agentassigne_id', $department->agents->pluck('id'))
                         ->with(['client:id,name,email', 'agent:id,name,email'])
                         ->get();

        return response()->json([
            'department' => $department->name,
            'tickets' => $tickets
        ]);
    }
}
