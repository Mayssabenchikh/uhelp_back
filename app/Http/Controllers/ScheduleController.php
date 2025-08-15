<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\User;

class ScheduleController extends Controller
{
    // Liste tous les horaires pour un agent
    public function index(User $agent)
    {
        if($agent->role !== 'agent') {
            return response()->json(['message'=>'Not an agent'], 404);
        }

        return response()->json($agent->schedules);
    }

    // Créer un nouvel horaire pour un agent
    public function store(Request $request, User $agent)
    {
        if($agent->role !== 'agent') {
            return response()->json(['message'=>'Not an agent'], 404);
        }

        $validated = $request->validate([
            'day_of_week' => 'required|integer|between:0,6',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
        ]);

        $schedule = $agent->schedules()->create($validated);

        return response()->json(['message'=>'Schedule created','schedule'=>$schedule],201);
    }

    // Afficher un horaire spécifique
    public function show(Schedule $schedule)
    {
        return response()->json($schedule->load('agent:id,name,email'));
    }

    // Mettre à jour un horaire
    public function update(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'day_of_week' => 'sometimes|integer|between:0,6',
            'start_time'  => 'sometimes|date_format:H:i',
            'end_time'    => 'sometimes|date_format:H:i|after:start_time',
        ]);

        $schedule->update($validated);

        return response()->json(['message'=>'Schedule updated','schedule'=>$schedule]);
    }

    // Supprimer un horaire
    public function destroy(Schedule $schedule)
    {
        $schedule->delete();
        return response()->json(['message'=>'Schedule deleted']);
    }
}
