<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AgentController extends Controller
{
    // List all agents
    public function index()
    {
        $agents = User::where('role', 'agent')
                      ->select('id','name','email')
                      ->orderBy('name')
                      ->get();

        return response()->json(['data' => $agents]);
    }

    // Create a new agent
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users',
            'password'      => 'required|string|min:8',
            'department_id' => 'required|exists:departments,id',
            'phone_number'  => 'nullable|string|max:20',
            'location'      => 'nullable|string|max:255', // ajouté
        ]);

        $agent = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'role'          => 'agent',
            'department_id' => $validated['department_id'],
            'phone_number'  => $validated['phone_number'] ?? null,
            'location'      => $validated['location'] ?? null, // ajouté
        ]);

        // après avoir créé $agent, envoyer notification de vérif email
        $agent->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Agent created successfully',
            'agent'   => $agent
        ], 201);
    }

    // Show a single agent
    public function show(User $agent)
    {
        if ($agent->role !== 'agent') {
            return response()->json(['message' => 'Not an agent'], 404);
        }
        return response()->json($agent->load('department'));
    }

    // Update an existing agent
    public function update(Request $request, User $agent)
    {
        if ($agent->role !== 'agent') {
            return response()->json(['message' => 'Not an agent'], 404);
        }

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'email'         => 'sometimes|string|email|max:255|unique:users,email,'.$agent->id,
            'password'      => 'sometimes|string|min:8',
            'department_id' => 'sometimes|exists:departments,id',
            'phone_number'  => 'nullable|string|max:20',
            'location'      => 'sometimes|nullable|string|max:255', // ajouté
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $agent->update($validated);

        return response()->json([
            'message' => 'Agent updated successfully',
            'agent'   => $agent
        ]);
    }

    // Delete an agent
    public function destroy(User $agent)
    {
        if ($agent->role !== 'agent') {
            return response()->json(['message' => 'Not an agent'], 404);
        }

        $agent->delete();

        return response()->json([
            'message' => 'Agent deleted successfully'
        ]);
    }
}
