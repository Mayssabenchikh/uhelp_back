<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportsController extends Controller
{
    /**
     * Retourne les données complètes du reporting (uniquement depuis la base de données)
     */
    public function index(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', '30days');
            $startDate = $this->getStartDate($dateRange);

            return response()->json([
                'overview' => $this->getOverviewMetrics($startDate),
                'ticket_volume' => $this->getTicketVolumeData($startDate),
                'status_distribution' => $this->getStatusDistribution($startDate),
                'priority_distribution' => $this->getPriorityDistribution($startDate),
                'response_time' => $this->getResponseTimeData($startDate),
                'agent_performance' => $this->getAgentPerformance($startDate),
                'satisfaction' => $this->getSatisfactionMetrics($startDate),
            ]);
        } catch (\Exception $e) {
            Log::error('Reports index error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'error' => 'Failed to generate reports',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Métriques pour les cartes du dashboard (calculées uniquement depuis la DB)
     */
    public function getOverviewMetrics($startDate)
    {
        try {
            $daysDiff = Carbon::now()->diffInDays($startDate);
            $previousPeriodStart = Carbon::parse($startDate)->subDays($daysDiff);

            // Total tickets période actuelle
            $totalTickets = Ticket::where('created_at', '>=', $startDate)->count();

            // Tickets résolus (utilise les valeurs de la colonne 'statut')
            $resolvedTickets = Ticket::where('created_at', '>=', $startDate)
                                    ->whereIn(DB::raw('LOWER(statut)'), ['closed', 'resolved', 'fermé', 'ferme'])
                                    ->count();

            // Période précédente
            $prevTotalTickets = Ticket::whereBetween('created_at', [$previousPeriodStart, $startDate])->count();
            $prevResolvedTickets = Ticket::whereBetween('created_at', [$previousPeriodStart, $startDate])
                                        ->whereIn(DB::raw('LOWER(statut)'), ['closed', 'resolved', 'fermé', 'ferme'])
                                        ->count();

            $resolutionRate = $totalTickets > 0 ? round(($resolvedTickets / $totalTickets) * 100, 1) : 0;
            $prevResolutionRate = $prevTotalTickets > 0 ? round(($prevResolvedTickets / $prevTotalTickets) * 100, 1) : 0;

            // Temps moyen de résolution en heures (created_at -> updated_at quand updated_at > created_at)
            $avgResponseTime = Ticket::where('created_at', '>=', $startDate)
                ->whereNotNull('updated_at')
                ->whereColumn('updated_at', '>', 'created_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_time')
                ->value('avg_time');

            $avgResponseTime = $avgResponseTime !== null ? round($avgResponseTime, 1) : null;

            // Customer satisfaction si colonne 'satisfaction' existe dans tickets
            $hasSatisfactionColumn = \Schema::hasColumn((new Ticket)->getTable(), 'satisfaction');
            $customerSatisfaction = null;
            if ($hasSatisfactionColumn) {
                $customerSatisfaction = Ticket::where('created_at', '>=', $startDate)
                                            ->whereNotNull('satisfaction')
                                            ->selectRaw('AVG(satisfaction) as avg_satisfaction')
                                            ->value('avg_satisfaction');

                $customerSatisfaction = $customerSatisfaction !== null ? round($customerSatisfaction, 2) : null;
            }

            return [
                'total_tickets' => [
                    'value' => $totalTickets,
                    'change' => $this->calculatePercentageChange($totalTickets, $prevTotalTickets),
                    'trend' => $totalTickets >= $prevTotalTickets ? 'up' : 'down'
                ],
                'resolution_rate' => [
                    'value' => $resolutionRate,
                    'change' => $this->calculatePercentageChange($resolutionRate, $prevResolutionRate),
                    'trend' => $resolutionRate >= $prevResolutionRate ? 'up' : 'down'
                ],
                'avg_response_time' => [
                    'value' => $avgResponseTime, // null si non calculable
                    'change' => null,
                    'trend' => null
                ],
                'customer_satisfaction' => [
                    'value' => $customerSatisfaction, // null si colonne absente
                    'change' => null,
                    'trend' => null
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Overview metrics error: ' . $e->getMessage());
            return [
                'total_tickets' => ['value' => 0, 'change' => 0, 'trend' => 'down'],
                'resolution_rate' => ['value' => 0, 'change' => 0, 'trend' => 'down'],
                'avg_response_time' => ['value' => null, 'change' => null, 'trend' => null],
                'customer_satisfaction' => ['value' => null, 'change' => null, 'trend' => null]
            ];
        }
    }

    /**
     * Volume de tickets par mois (depuis la DB)
     */
    public function getTicketVolumeData($startDate)
    {
        try {
            $results = Ticket::select(
                    DB::raw('DATE_FORMAT(created_at, "%b") as month'),
                    DB::raw('COUNT(*) as tickets'),
                    DB::raw('SUM(CASE WHEN LOWER(statut) IN ("closed", "resolved", "fermé", "ferme") THEN 1 ELSE 0 END) as resolved')
                )
                ->where('created_at', '>=', $startDate)
                ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
                ->orderBy('created_at')
                ->get();

            return $results->map(function($item) {
                return [
                    'month' => $item->month,
                    'tickets' => (int)$item->tickets,
                    'resolved' => (int)$item->resolved
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Ticket volume error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Répartition par statut (depuis la DB)
     */
    public function getStatusDistribution($startDate)
    {
        try {
            $statuses = Ticket::select('statut', DB::raw('COUNT(*) as count'))
                             ->where('created_at', '>=', $startDate)
                             ->whereNotNull('statut')
                             ->groupBy('statut')
                             ->get();

            $colors = [
                'closed' => '#22c55e',
                'fermé' => '#22c55e',
                'resolved' => '#22c55e',
                'open' => '#f97316',
                'ouvert' => '#f97316',
                'in_progress' => '#3b82f6',
                'en_cours' => '#3b82f6',
                'pending' => '#eab308',
                'en_attente' => '#eab308'
            ];

            return $statuses->map(function($item) use ($colors) {
                $key = strtolower($item->statut);
                return [
                    'name' => ucfirst(str_replace('_', ' ', $item->statut)),
                    'value' => (int)$item->count,
                    'color' => $colors[$key] ?? '#6b7280'
                ];
            })->values()->toArray();
        } catch (\Exception $e) {
            Log::error('Status distribution error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Répartition par priorité (depuis la DB)
     */
    public function getPriorityDistribution($startDate)
    {
        try {
            $results = Ticket::select('priorite as priority', DB::raw('COUNT(*) as count'))
                        ->where('created_at', '>=', $startDate)
                        ->whereNotNull('priorite')
                        ->where('priorite', '!=', '')
                        ->groupBy('priorite')
                        ->orderByDesc('count')
                        ->get();

            return $results->map(function($item) {
                return [
                    'priority' => ucfirst($item->priority),
                    'count' => (int)$item->count
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Priority distribution error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Temps moyen de réponse par semaine (calcul simple depuis la DB)
     */
    public function getResponseTimeData($startDate)
    {
        try {
            // Exemple: moyenne hebdomadaire des temps de résolution (heures)
            $results = Ticket::where('created_at', '>=', $startDate)
                        ->whereNotNull('updated_at')
                        ->whereColumn('updated_at', '>', 'created_at')
                        ->select(
                            DB::raw('WEEK(created_at, 1) as week_number'),
                            DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avgResponse')
                        )
                        ->groupBy('week_number')
                        ->orderBy('week_number')
                        ->get();

            return $results->map(function($r) {
                return [
                    'week' => 'Week ' . $r->week_number,
                    'avgResponse' => round($r->avgResponse, 2)
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Response time error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Performance des agents (calculée depuis la DB)
     */
    public function getAgentPerformance($startDate)
    {
        try {
            // Suppose que la table tickets contient agent_id référant aux users
            $agents = User::where('role', 'agent')->get();

            if ($agents->isEmpty()) {
                return [];
            }

            $data = $agents->map(function($agent) use ($startDate) {
                $solved = Ticket::where('agent_id', $agent->id)
                                ->where('created_at', '>=', $startDate)
                                ->whereIn(DB::raw('LOWER(statut)'), ['closed', 'resolved', 'fermé', 'ferme'])
                                ->count();

                $hasSatisfactionColumn = \Schema::hasColumn((new Ticket)->getTable(), 'satisfaction');
                $satisfaction = null;
                if ($hasSatisfactionColumn) {
                    $satisfaction = Ticket::where('agent_id', $agent->id)
                                            ->where('created_at', '>=', $startDate)
                                            ->whereNotNull('satisfaction')
                                            ->selectRaw('AVG(satisfaction) as avg_satisfaction')
                                            ->value('avg_satisfaction');

                    $satisfaction = $satisfaction !== null ? round($satisfaction, 2) : null;
                }

                return [
                    'agent' => strtolower($agent->name),
                    'solved' => (int)$solved,
                    'satisfaction' => $satisfaction
                ];
            })->sortByDesc('solved')->take(5)->values()->toArray();

            return $data;
        } catch (\Exception $e) {
            Log::error('Agent performance error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Métriques de satisfaction (depuis la DB si disponible)
     */
    public function getSatisfactionMetrics($startDate)
    {
        try {
            $hasSatisfactionColumn = \Schema::hasColumn((new Ticket)->getTable(), 'satisfaction');
            if (! $hasSatisfactionColumn) {
                return [
                    'overall_rating' => null,
                    'response_rate' => null,
                    'resolution_rate' => null,
                    'trends' => []
                ];
            }

            $overall = Ticket::where('created_at', '>=', $startDate)
                        ->whereNotNull('satisfaction')
                        ->selectRaw('AVG(satisfaction) as overall_rating')
                        ->value('overall_rating');

            $responseRate = Ticket::where('created_at', '>=', $startDate)
                        ->whereNotNull('updated_at')
                        ->whereColumn('updated_at', '>', 'created_at')
                        ->count();

            $total = Ticket::where('created_at', '>=', $startDate)->count();

            $responseRatePercent = $total > 0 ? round(($responseRate / $total) * 100, 1) : null;

            $resolved = Ticket::where('created_at', '>=', $startDate)
                        ->whereIn(DB::raw('LOWER(statut)'), ['closed', 'resolved', 'fermé', 'ferme'])
                        ->count();

            $resolutionRatePercent = $total > 0 ? round(($resolved / $total) * 100, 1) : null;

            return [
                'overall_rating' => $overall !== null ? round($overall, 2) : null,
                'response_rate' => $responseRatePercent,
                'resolution_rate' => $resolutionRatePercent,
                'trends' => []
            ];
        } catch (\Exception $e) {
            Log::error('Satisfaction metrics error: ' . $e->getMessage());
            return [
                'overall_rating' => null,
                'response_rate' => null,
                'resolution_rate' => null,
                'trends' => []
            ];
        }
    }

    /**
     * Export CSV ou JSON
     */
    public function export(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', '30days');
            $format = $request->get('format', 'csv');
            $startDate = $this->getStartDate($dateRange);

            $data = [
                'overview' => $this->getOverviewMetrics($startDate),
                'tickets' => Ticket::where('created_at', '>=', $startDate)->get(),
                'agents' => $this->getAgentPerformance($startDate)
            ];

            if($format === 'csv') {
                return $this->exportToCsv($data);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Reports export error: ' . $e->getMessage());

            return response()->json([
                'error' => 'Failed to export reports',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export CSV
     */
    private function exportToCsv($data)
    {
        $fileName = 'reports_export_' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID','Title','Status','Priority','Created At']);

            foreach($data['tickets'] as $ticket){
                fputcsv($file, [
                    $ticket->id ?? '',
                    $ticket->titre ?? $ticket->title ?? '',
                    $ticket->statut ?? $ticket->status ?? '',
                    $ticket->priorite ?? $ticket->priority ?? '',
                    $ticket->created_at ? $ticket->created_at->format('Y-m-d H:i:s') : ''
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Pourcentage de variation
     */
    private function calculatePercentageChange($current, $previous)
    {
        if($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Début de la période
     */
    private function getStartDate($dateRange)
    {
        return match($dateRange) {
            '7days' => Carbon::now()->subDays(7),
            '30days' => Carbon::now()->subDays(30),
            '90days' => Carbon::now()->subDays(90),
            '1year' => Carbon::now()->subYear(),
            default => Carbon::now()->subDays(30),
        };
    }
}
