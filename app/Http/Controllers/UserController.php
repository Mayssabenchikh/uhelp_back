<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\UserResource;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Response;

class UserController extends Controller
{
    // Lister tous les utilisateurs
   public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        $query = User::with('department');

        // Recherche (name, email, id)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $isNumeric = ctype_digit(strval($search));
            $query->where(function ($q) use ($search, $isNumeric) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
                if ($isNumeric) {
                    $q->orWhere('id', (int)$search);
                }
            });
        }

        // Role
        if ($request->filled('role') && $request->get('role') !== 'all') {
            $query->where('role', $request->get('role'));
        }

        // Status (active / inactive) basé sur email_verified_at
        if ($request->filled('status') && $request->get('status') !== 'all') {
            $status = strtolower($request->get('status'));
            if ($status === 'active') {
                $query->whereNotNull('email_verified_at');
            } elseif ($status === 'inactive') {
                $query->whereNull('email_verified_at');
            }
        }

        // Department filter (par department_id)
        if ($request->filled('department') && $request->get('department') !== 'all') {
            $query->where('department_id', $request->get('department'));
        }

        // Has phone
        if ($request->filled('has_phone')) {
            $query->whereNotNull('phone_number')->where('phone_number', '!=', '');
        }

        // Has profile photo
        if ($request->filled('has_photo')) {
            $query->whereNotNull('profile_photo')->where('profile_photo', '!=', '');
        }

        // FIX: ne pas aliaser, pour que whenCounted('createdTickets') fonctionne
        $query->withCount([
            'createdTickets',
            'resolvedTickets',
        ]);

        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Retourne la collection via UserResource (paginée -> data + meta)
        return UserResource::collection($users);
    }


    // Afficher un utilisateur spécifique
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    // Créer un nouvel utilisateur (avec upload optionnel de photo)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:6',
            'role'          => ['required', Rule::in(['admin', 'agent', 'client'])],
            'profile_photo' => 'nullable|image|max:2048',
            'phone_number'  => 'nullable|string|max:20',
            'location'      => 'nullable|string|max:255', // ajouté
        ]);

        // Hash du password
        $validated['password'] = Hash::make($validated['password']);

        // Gérer l'upload si présent
        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile_photos', 'public');
            $validated['profile_photo'] = $path;
        }

        $user = User::create($validated);

        // après avoir créé $user
        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

   // UserController.php

public function update(Request $request, $id)
{
    $user = User::findOrFail($id);

    $validated = $request->validate([
        'name'          => 'sometimes|required|string|max:255',
        'email'         => [
            'sometimes', 'required', 'email', 'max:255',
            Rule::unique('users')->ignore($user->id),
        ],
        'password'      => 'nullable|string|min:6',
        'role'          => 'sometimes|required|in:agent,client',
        'phone_number'  => 'nullable|string|max:20',
        'location'      => 'nullable|string|max:255',
        'department_id' => 'nullable|exists:departments,id',
        'profile_photo' => 'nullable|image|max:2048', // max 2MB
        'remove_profile_photo' => 'nullable|boolean',
    ]);

    // 🔹 Mise à jour des champs simples
    $user->fill($validated);

    // 🔹 Gestion du mot de passe
    if (!empty($validated['password'])) {
        $user->password = Hash::make($validated['password']);
    }

    // 🔹 Suppression de la photo si demandé
    if ($request->boolean('remove_profile_photo')) {
        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }
        $user->profile_photo = null;
    }

    // 🔹 Upload d'une nouvelle photo
    if ($request->hasFile('profile_photo')) {
        // Supprimer l'ancienne
        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $path = $request->file('profile_photo')->store('profile_photos', 'public');
        $user->profile_photo = $path;
    }

    $user->save();

    return new UserResource($user->load('department'));
}


    // Supprimer un utilisateur
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Supprimer la photo éventuelle
        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
public function export()
{
    $fileName = 'users_export_' . now()->format('Y-m-d') . '.csv';
    $users = User::all(['id', 'name', 'email', 'role', 'phone_number', 'department_id', 'created_at']);

    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => "attachment; filename=\"$fileName\"",
    ];

    $callback = function() use ($users) {
        $file = fopen('php://output', 'w');

        // En-têtes CSV
        fputcsv($file, ['ID', 'Name', 'Email', 'Role', 'Phone', 'Department ID', 'Created At']);

        // Contenu CSV
        foreach ($users as $user) {
            fputcsv($file, [
                $user->id,
                $user->name,
                $user->email,
                $user->role,
                $user->phone_number,
                $user->department_id,
                $user->created_at,
            ]);
        }

        fclose($file);
    };

    return Response::stream($callback, 200, $headers);
}
    // Retourne l'utilisateur connecté (déjà présent)
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }
}
