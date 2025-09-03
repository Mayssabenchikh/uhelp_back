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
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
   public function index(Request $request)
{
    $perPage = $request->get('per_page', 15);
    $user = Auth::user();

    $query = Ticket::with(['client', 'agent'])->orderBy('created_at', 'desc');

    // --- Filtering from query params ---
    // search (titre, description, ticket_id formaté, client name)
    $search = $request->get('search', null);
    if ($search) {
        // determine if search is purely numeric (then we can match id)
        $isNumeric = ctype_digit(strval($search));

        $query->where(function ($q) use ($search, $isNumeric) {
            // titre / description
            $q->where('titre', 'like', '%' . $search . '%')
              ->orWhere('description', 'like', '%' . $search . '%');

            // si l'utilisateur a tapé un nombre, on compare à l'id
            if ($isNumeric) {
                $q->orWhere('id', (int)$search);
            }

            // Pour supporter la recherche par ticket_id formaté (TK-001, TK-1, etc.)
            // on construit la version formatée à la volée : CONCAT('TK-', LPAD(id,3,'0')).
            // NOTE: cette condition utilise une expression SQL (no colonne ticket_id requise).
            $q->orWhereRaw("CONCAT('TK-', LPAD(`id`, 3, '0')) LIKE ?", ['%' . $search . '%']);

            // recherche par nom du client
            $q->orWhereHas('client', function ($qc) use ($search) {
                $qc->where('name', 'like', '%' . $search . '%');
            });
        });
    }

    // status
    if ($request->filled('status')) {
        $query->where('statut', $request->get('status'));
    }

    // priority (priorite)
    if ($request->filled('priority')) {
        $query->where('priorite', $request->get('priority'));
    }

    // category
    if ($request->filled('category')) {
        $query->where('category', $request->get('category'));
    }

    // assigned agent (accept either assigned_agent or agentassigne_id)
    $assignedAgent = $request->get('assigned_agent', $request->get('agentassigne_id', null));
    if (!is_null($assignedAgent) && $assignedAgent !== '') {
        $query->where('agentassigne_id', $assignedAgent);
    }

    if ($user->role === 'client') {
        // Un client ne voit que ses tickets
        $query->where('client_id', $user->id);
    } elseif ($user->role === 'agent') {
        // Un agent ne voit que les tickets qui lui sont assignés
        $query->where('agentassigne_id', $user->id);
    }
    // Si admin → pas de restriction, il voit tout

    $tickets = $query->paginate($perPage);

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
                    'category' => $data['category'] ?? null,
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

    public function assignAgent(Request $request, Ticket $ticket)
    {
        $user = $request->user();

        // sécurité : s'assurer que l'utilisateur est connecté et a le bon rôle
        if (!$user || ! in_array($user->role, ['admin', 'agent'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // accepter agent_id ou agentId (fronts différents)
        $input = $request->all();
        $agentIdRaw = $request->input('agent_id', $request->input('agentId', null));

        $validator = Validator::make(['agent_id' => $agentIdRaw], [
            'agent_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $agentId = (int) $agentIdRaw;

        // Vérifie que l'utilisateur sélectionné est bien un agent
        $agent = User::where('id', $agentId)->where('role', 'agent')->first();

        if (! $agent) {
            return response()->json(['message' => 'Agent introuvable ou rôle invalide'], 422);
        }

        $oldAgent = $ticket->agentassigne_id;

        // pas d'opération si on ré-assigne au même agent
        if (!is_null($oldAgent) && (int)$oldAgent === $agentId) {
            return response()->json([
                'message' => 'No change: agent already assigned',
                'ticket' => $ticket->load('client','agent')
            ], 200);
        }

        try {
            DB::transaction(function () use ($ticket, $agentId, &$oldAgent) {
                // reload for update protection
                $ticket = Ticket::lockForUpdate()->find($ticket->id);
                if (! $ticket) {
                    throw new \RuntimeException('Ticket introuvable pendant la transaction');
                }

                $oldAgent = $ticket->agentassigne_id;
                $ticket->agentassigne_id = $agentId;
                $ticket->save();
            });

            // notifier le nouvel agent (on passe l'ancien agent en second param si tu l'utilises)
            try {
                $agent->notify(new TicketAssigned($ticket->load('client','agent'), $oldAgent));
                // notifier l'ancien agent si différent
                if ($oldAgent && (int)$oldAgent !== (int)$agentId) {
                    $old = User::find($oldAgent);
                    if ($old) {
                        $old->notify(new TicketAssigned($ticket->load('client','agent'), null));
                    }
                }
            } catch (\Throwable $notifyEx) {
                // ne pas faire échouer l'action principale à cause d'une notification qui plante
                Log::warning('Notification failed on assignAgent: ' . $notifyEx->getMessage());
            }

            return response()->json([
                'message' => 'Agent assigned',
                'ticket' => $ticket->load('client','agent')
            ], 200);
        } catch (\Throwable $e) {
            Log::error('assignAgent error: ' . $e->getMessage(), ['ticket_id' => $ticket->id ?? null, 'agent_id' => $agentId]);
            return response()->json(['message' => 'Erreur lors de l\'assignation', 'error' => $e->getMessage()], 500);
        }
    }
}
