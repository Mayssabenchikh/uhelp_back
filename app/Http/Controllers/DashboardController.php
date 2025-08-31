<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Http\Resources\TicketResource;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;

class DashboardController extends Controller
{
    /**
     * Retourne les stats et récents tickets formatés pour le front.
     * Route protégée par auth:sanctum.
     */
    public function index(Request $request)
{
    $totalTickets = \App\Models\Ticket::count();
    $openTickets = \App\Models\Ticket::where('statut', 'open')->count();
    $resolvedTickets = \App\Models\Ticket::where('statut', 'resolved')->count();

    $since = \Carbon\Carbon::now()->subDays(30);
    $activeCustomers = \App\Models\Ticket::where('created_at', '>=', $since)
                             ->distinct('client_id')
                             ->count('client_id');

    $recent = \App\Models\Ticket::with(['client:id,name', 'agent:id,name'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

    // IMPORTANT : on transforme explicitement la collection en array pour éviter le wrapper "data"
    $recentArray = \App\Http\Resources\TicketResource::collection($recent)->toArray($request);

    return response()->json([
        'stats' => [
            'total_tickets' => $totalTickets,
            'open_tickets' => $openTickets,
            'resolved_tickets' => $resolvedTickets,
            'active_customers' => $activeCustomers,
        ],
        'recent_tickets' => $recentArray,
    ]);
}

}
