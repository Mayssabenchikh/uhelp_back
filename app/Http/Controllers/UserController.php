<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\UserResource;

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

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'email' => ['sometimes','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'password' => ['sometimes','nullable','string','min:6'],
            'role' => ['sometimes', Rule::in(['admin','agent','client'])],
            'profile_photo' => ['sometimes','nullable','image','max:2048'],
            'phone_number' => ['sometimes','nullable','string','max:20'],
            'location' => ['sometimes','nullable','string','max:255'], // ajouté
        ]);

        // Hasher le mot de passe si fourni
        if (array_key_exists('password', $validated) && !empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Gérer l'upload
        if ($request->hasFile('profile_photo')) {
            // supprimer l'ancienne si existe
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $path = $request->file('profile_photo')->store('profile_photos', 'public');
            $validated['profile_photo'] = $path;
        }

        $user->update($validated);

        // Ajouter une url publique pour l'avatar (pratique côté frontend)
        $profilePhotoUrl = $user->profile_photo ? asset('storage/' . $user->profile_photo) : null;
        $user->profile_photo_url = $profilePhotoUrl;

        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
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

    // Retourne l'utilisateur connecté (déjà présent)
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }
}
