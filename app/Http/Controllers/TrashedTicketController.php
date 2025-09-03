<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Http\Resources\TicketResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; // en haut du fichier si pas déjà présent
use Carbon\Carbon; // en haut du fichier si pas déjà

class TrashedTicketController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/tickets/trashed
     * Query params: page, per_page, q or search, sort (ex: created_at|desc)**/

public function index(Request $request)
{
    $perPage = (int) $request->query('per_page', 15);

    // recherche (q ou search)
    $q = $request->query('q', $request->query('search', null));

    // tri demandé (ex: "priority|desc" ou "created_at|asc")
    $sort = $request->query('sort', 'created_at|desc');
    [$sortByRequested, $sortDirRequested] = array_pad(explode('|', $sort), 2, null);
    $sortKey = $sortByRequested ? strtolower($sortByRequested) : 'created_at';
    $sortDir = in_array(strtolower($sortDirRequested), ['asc', 'desc']) ? strtolower($sortDirRequested) : 'desc';

    // filtres
    $status = $request->query('status', null);
    $priority = $request->query('priority', null);
    $category = $request->query('category', null);
    $deletedFrom = $request->query('deleted_from', $request->query('deleted_at_from', null));
    $deletedTo = $request->query('deleted_to', $request->query('deleted_at_to', null));

    // whitelist / sanitisation pour filtrage
    $allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];
    $allowedPriorities = ['low', 'medium', 'high'];

    // détecte quelles colonnes existent réellement dans la table tickets
    $possibleStatusCols = ['status', 'etat', 'statut', 'original_status'];
    $possiblePriorityCols = ['priority', 'priorite', 'importance'];

    $statusColumn = null;
    foreach ($possibleStatusCols as $col) {
        if (Schema::hasColumn('tickets', $col)) {
            $statusColumn = $col;
            break;
        }
    }

    $priorityColumn = null;
    foreach ($possiblePriorityCols as $col) {
        if (Schema::hasColumn('tickets', $col)) {
            $priorityColumn = $col;
            break;
        }
    }

    // colonnes valables pour le tri (map requestKey => actualColumn or null)
    $sortableCandidates = [
        'priority'   => $priorityColumn,
        'priorite'   => $priorityColumn,
        'status'     => $statusColumn,
        'statut'     => $statusColumn,
        'created_at' => Schema::hasColumn('tickets', 'created_at') ? 'created_at' : null,
        'deleted_at' => Schema::hasColumn('tickets', 'deleted_at') ? 'deleted_at' : null,
        'id'         => Schema::hasColumn('tickets', 'id') ? 'id' : null,
        'titre'      => Schema::hasColumn('tickets', 'titre') ? 'titre' : null,
    ];

    // déterminer la vraie colonne à utiliser pour ORDER BY
    $sortBy = $sortableCandidates[$sortKey] ?? null;
    if (!$sortBy) {
        // fallback : try using the requested key as-is if it exists, otherwise default to created_at
        if ($sortKey && Schema::hasColumn('tickets', $sortKey)) {
            $sortBy = $sortKey;
        } else {
            $sortBy = Schema::hasColumn('tickets', 'created_at') ? 'created_at' : 'id';
        }
    }

    $query = Ticket::onlyTrashed()
        ->with(['agent:id,name', 'client:id,name']);

    // Recherche flexible : titre, client.name, TK-123, id
    if ($q) {
        $query->where(function ($qb) use ($q) {
            $qb->where('titre', 'like', "%{$q}%")
               ->orWhereHas('client', function ($cqb) use ($q) {
                   $cqb->where('name', 'like', "%{$q}%");
               });

            if (preg_match('/^TK-?0*(\d+)$/i', $q, $m)) {
                $id = (int) $m[1];
                $qb->orWhere('id', $id);
            } elseif (ctype_digit($q)) {
                $qb->orWhere('id', (int) $q);
            } else {
                $qb->orWhereRaw("CONCAT('TK-', LPAD(`id`, 3, '0')) LIKE ?", ["%{$q}%"]);
            }
        });
    }

    // applique filtrage sur status si colonne dispo
    if ($status && $statusColumn && in_array(strtolower($status), $allowedStatuses, true)) {
        $query->where($statusColumn, strtolower($status));
    }

    // applique filtrage sur priority si colonne dispo
    if ($priority && $priorityColumn && in_array(strtolower($priority), $allowedPriorities, true)) {
        $query->where($priorityColumn, strtolower($priority));
    }

    // category (teste different noms)
    if ($category) {
        if (Schema::hasColumn('tickets', 'category')) {
            $query->where('category', $category);
        } elseif (Schema::hasColumn('tickets', 'categorie')) {
            $query->where('categorie', $category);
        }
    }

    // filtre par date de suppression
    if ($deletedFrom && $deletedTo) {
        $query->whereBetween('deleted_at', [$deletedFrom, $deletedTo]);
    } elseif ($deletedFrom) {
        $query->where('deleted_at', '>=', $deletedFrom);
    } elseif ($deletedTo) {
        $query->where('deleted_at', '<=', $deletedTo);
    }

    // final orderBy sur la colonne validée
    $paginator = $query->orderBy($sortBy, $sortDir)
                       ->paginate($perPage)
                       ->appends($request->query());

    $resources = TicketResource::collection($paginator->getCollection())->resolve();

    $meta = [
        'current_page' => $paginator->currentPage(),
        'last_page'    => $paginator->lastPage(),
        'per_page'     => $paginator->perPage(),
        'total'        => $paginator->total(),
        'from'         => $paginator->firstItem(),
        'to'           => $paginator->lastItem(),
    ];

    return response()->json([
        'data' => $resources,
        'meta' => $meta,
    ], 200);
}


    public function restore($id)
    {
        $ticket = Ticket::withTrashed()->findOrFail($id);
        $ticket->restore();
        $fresh = $ticket->fresh()->load(['agent:id,name', 'client:id,name']);
        return (new TicketResource($fresh))->response()->setStatusCode(200);
    }

    public function bulkRestore(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer'
        ]);
        Ticket::onlyTrashed()->whereIn('id', $data['ids'])->restore();
        return response()->json(['message' => 'Restored'], 200);
    }

    public function forceDelete($id)
    {
        $ticket = Ticket::withTrashed()->findOrFail($id);
        $ticket->forceDelete();
        return response()->json(['message' => 'Permanently deleted'], 200);
    }

    public function bulkForceDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer'
        ]);
        DB::transaction(function () use ($data) {
            Ticket::onlyTrashed()->whereIn('id', $data['ids'])->forceDelete();
        });
        return response()->json(['message' => 'Permanently deleted'], 200);
    }
   
public function autoCleanOld(Request $request)
{
    // autorisation déjà via middleware auth:sanctum dans __construct
    $request->validate([
        'days' => 'nullable|integer|min:1|max:3650',
    ]);

    $days = (int) ($request->input('days', 30));
    $cutoff = Carbon::now()->subDays($days);

    // compter avant suppression
    $count = 0;

    DB::transaction(function () use ($cutoff, &$count) {
        $qb = Ticket::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $count = $qb->count();
        // supprimer définitivement
        $qb->each(function($t) {
            $t->forceDelete();
        });
        // alternative plus rapide (mais attention aux triggers/relations):
        // Ticket::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
    });

    return response()->json([
        'message' => 'Auto-clean completed',
        'deleted' => $count,
        'cutoff' => $cutoff->toDateTimeString()
    ], 200);
}

}
