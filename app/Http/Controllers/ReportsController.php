<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsController extends Controller
{
    /**
     * Get comprehensive analytics data
     */
    public function index(Request $request)
    {
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
    }

    /**
     * Get overview metrics for dashboard cards
     */
    public function getOverviewMetrics($startDate = null)
    {
        if (!$startDate) {
            $startDate = $this->getStartDate('30days');
        }

        $previousPeriodStart = Carbon::parse($startDate)->subDays(
            Carbon::now()->diffInDays($startDate)
        );

        // Current period metrics
        $totalTickets = Ticket::where('created_at', '>=', $startDate)->count();
        $resolvedTickets = Ticket::where('created_at', '>=', $startDate)
                                ->where('statut', 'closed')
                                ->count();

        $avgResponseTime = Ticket::where('created_at', '>=', $startDate)
                                ->whereNotNull('agentassigne_id')
                                ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, updated_at)'));

        // Previous period for comparison
        $prevTotalTickets = Ticket::whereBetween('created_at', [$previousPeriodStart, $startDate])->count();
        $prevResolvedTickets = Ticket::whereBetween('created_at', [$previousPeriodStart, $startDate])
                                    ->where('statut', 'closed')
                                    ->count();

        $prevAvgResponseTime = Ticket::whereBetween('created_at', [$previousPeriodStart, $startDate])
                                    ->whereNotNull('agentassigne_id')
                                    ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, updated_at)'));

        $resolutionRate = $totalTickets > 0 ? round(($resolvedTickets / $totalTickets) * 100, 1) : 0;
        $prevResolutionRate = $prevTotalTickets > 0 ? round(($prevResolvedTickets / $prevTotalTickets) * 100, 1) : 0;

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
                'value' => round($avgResponseTime ?? 0, 1),
                'change' => $this->calculatePercentageChange($avgResponseTime ?? 0, $prevAvgResponseTime ?? 0),
                'trend' => ($avgResponseTime ?? 0) <= ($prevAvgResponseTime ?? 0) ? 'up' : 'down' // Lower is better for response time
            ],
            'customer_satisfaction' => [
                'value' => 4.7, // Mock data - you can implement actual satisfaction tracking
                'change' => 4.3,
                'trend' => 'up'
            ]
        ];
    }

    /**
     * Get ticket volume trend data
     */
    public function getTicketVolumeData($startDate = null)
    {
        if (!$startDate) {
            $startDate = $this->getStartDate('30days');
        }

        return Ticket::select(
                DB::raw('DATE_FORMAT(created_at, "%b") as month'),
                DB::raw('COUNT(*) as tickets'),
                DB::raw('SUM(CASE WHEN statut = "closed" THEN 1 ELSE 0 END) as resolved')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('YEAR(created_at), MONTH(created_at)'))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get status distribution
     */
    public function getStatusDistribution($startDate = null)
    {
        if (!$startDate) {
            $startDate = $this->getStartDate('30days');
        }

        $statuses = Ticket::select('statut', DB::raw('COUNT(*) as count'))
                         ->where('created_at', '>=', $startDate)
                         ->groupBy('statut')
                         ->get();

        $colors = [
            'closed' => '#22c55e',
            'open' => '#f97316',
            'in_progress' => '#3b82f6',
            'pending' => '#eab308',
            'resolved' => '#22c55e'
        ];

        return $statuses->map(function($item) use ($colors) {
            return [
                'name' => ucfirst(str_replace('_', ' ', $item->statut)),
                'value' => $item->count,
                'color' => $colors[$item->statut] ?? '#6b7280'
            ];
        });
    }

    /**
     * Get priority distribution
     */
    public function getPriorityDistribution($startDate = null)
    {
        if (!$startDate) {
            $startDate = $this->getStartDate('30days');
        }

        return Ticket::select('priorite as priority', DB::raw('COUNT(*) as count'))
                    ->where('created_at', '>=', $startDate)
                    ->whereNotNull('priorite')
                    ->groupBy('priorite')
                    ->orderBy('count', 'desc')
                    ->get()
                    ->map(function($item) {
                        return [
                            'priority' => ucfirst($item->priority),
                            'count' => $item->count
                        ];
                    });
    }

    /**
     * Get response time trend data
     */
    public function getResponseTimeData($startDate = null)
    {
        if (!$startDate) {
            $startDate = $this->getStartDate('30days');
        }

        return Ticket::select(
                DB::raw('WEEK(created_at) as week_num'),
                DB::raw('CONCAT("Week ", WEEK(created_at) - WEEK(?) + 1) as week'),
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avgResponse')
            )
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('agentassigne_id')
            ->groupBy(DB::raw('WEEK(created_at)'))
            ->orderBy('week_num')
            ->get()
            ->map(function($item) {
                return [
                    'week' => $item->week,
                    'avgResponse' => round($item->avgResponse, 1)
                ];
            });
    }

    /**
     * Get agent performance data
     */
    public function getAgentPerformance($startDate = null)
    {
        if (!$startDate) {
            $startDate = $this->getStartDate('30days');
        }

        return User::where('role', 'agent')
                  ->withCount([
                      'assignedTickets as solved' => function($query) use ($startDate) {
                          $query->where('created_at', '>=', $startDate)
                                ->where('statut', 'closed');
                      }
                  ])
                  ->having('solved', '>', 0)
                  ->orderBy('solved', 'desc')
                  ->get()
                  ->map(function($agent) {
                      return [
                          'agent' => strtolower($agent->name),
                          'solved' => $agent->solved,
                          'satisfaction' => round(4.5 + (rand(-3, 5) / 10), 1) // Mock satisfaction score
                      ];
                  });
    }

    /**
     * Get satisfaction metrics
     */
    public function getSatisfactionMetrics($startDate = null)
    {
        // Mock data - implement actual satisfaction tracking based on your needs
        return [
            'overall_rating' => 4.7,
            'response_rate' => 92,
            'resolution_rate' => 87,
            'trends' => [
                'overall_rating' => 0.2,
                'response_rate' => 5,
                'resolution_rate' => 3
            ]
        ];
    }

    /**
     * Export reports data
     */
    public function export(Request $request)
    {
        $dateRange = $request->get('date_range', '30days');
        $format = $request->get('format', 'csv');
        $startDate = $this->getStartDate($dateRange);

        $data = [
            'overview' => $this->getOverviewMetrics($startDate),
            'tickets' => Ticket::with(['client:id,name,email', 'agent:id,name,email'])
                              ->where('created_at', '>=', $startDate)
                              ->get(),
            'agents' => $this->getAgentPerformance($startDate)
        ];

        if ($format === 'csv') {
            return $this->exportToCsv($data);
        }

        return response()->json($data);
    }

    /**
     * Export data to CSV format
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

            // Export tickets data
            fputcsv($file, ['ID', 'Title', 'Status', 'Priority', 'Client', 'Agent', 'Created At']);
            
            foreach ($data['tickets'] as $ticket) {
                fputcsv($file, [
                    $ticket->id,
                    $ticket->titre,
                    $ticket->statut,
                    $ticket->priorite,
                    $ticket->client?->name,
                    $ticket->agent?->name,
                    $ticket->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Calculate percentage change between two values
     */
    private function calculatePercentageChange($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get start date based on date range
     */
    private function getStartDate($dateRange)
    {
        switch ($dateRange) {
            case '7days':
                return Carbon::now()->subDays(7);
            case '30days':
                return Carbon::now()->subDays(30);
            case '90days':
                return Carbon::now()->subDays(90);
            case '1year':
                return Carbon::now()->subYear();
            default:
                return Carbon::now()->subDays(30);
        }
    }
}