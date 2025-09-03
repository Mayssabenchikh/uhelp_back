<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Http\Resources\TicketResource;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

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
        $totalCustomers = 0;

        // 1) Si tu utilises spatie/laravel-permission -> User::role('client')->count()
        try {
            if (method_exists(User::class, 'role')) {
                // If the role() scope exists on User (spatie) use it.
                $totalCustomers = User::role('client')->count();
            }
        } catch (\Throwable $e) {
            // ignore and try next strategy
        }

        // 2) Sinon si tu as une colonne 'role' dans users
        if ($totalCustomers === 0 && Schema::hasColumn('users', 'role')) {
            $totalCustomers = User::where('role', 'client')->count();
        }

        // 3) Fallback: compte distinct des customer_id dans tickets
        if ($totalCustomers === 0) {
            // On suppose la colonne tickets.customer_id (ou customer_id)
            if (Schema::hasColumn('tickets', 'customer_id')) {
                // distinct count via query builder
                $totalCustomers = DB::table('tickets')->select('customer_id')->distinct()->count('customer_id');
            } else {
                // dernier recours: essayer customer_id ou client_id
                if (Schema::hasColumn('tickets', 'client_id')) {
                    $totalCustomers = DB::table('tickets')->select('client_id')->distinct()->count('client_id');
                } else {
                    // impossible d'inférer -> 0
                    $totalCustomers = 0;
                }
            }
        }

        return response()->json([
            'stats' => [
                'total_tickets' => $totalTickets,
                'open_tickets' => $openTickets,
                'resolved_tickets' => $resolvedTickets,
                'active_customers' => $activeCustomers,
                'total_customers' => $totalCustomers,

            ],
            'recent_tickets' => $recentArray,
        ]);
    }
}
