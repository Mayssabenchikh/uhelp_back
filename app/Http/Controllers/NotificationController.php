<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index()
    {
        // Récupère les dernières notifications
        $notifications = DB::table('notifications')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Transforme les données pour le frontend
        $activities = $notifications->map(function ($notif) {
            $data = json_decode($notif->data, true);

            return [
                'id' => $notif->id,
                'type' => $notif->type, // ticket_assigned, ticket_status_changed…
                'title' => $data['titre'] ?? 'Sans titre',
                'description' => $data['previous_agent'] ?? 'Nouvelle notification',
                'time' => $notif->created_at,
            ];
        });

        return response()->json($activities);
    }
}
