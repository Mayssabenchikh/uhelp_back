<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // Lister tous les utilisateurs
    public function index()
    {
        $users = User::all();
        return response()->json($users);
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

    // Mettre à jour un utilisateur (y compris remplacer la photo)
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
    'name'          => 'required|string|max:255',
    'email'         => 'required|email|unique:users,email',
    'password'      => 'required|string|min:6',
    'role'          => ['required', Rule::in(['admin', 'agent', 'client'])],
    'profile_photo' => 'nullable|image|max:2048',
    'phone_number'  => 'nullable|string|max:20',
]);

        // Si mot de passe fourni -> hasher
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Si nouvelle photo fournie -> supprimer ancienne (si existe) et stocker la nouvelle
        if ($request->hasFile('profile_photo')) {
            // supprimer l'ancienne photo si présente
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $path = $request->file('profile_photo')->store('profile_photos', 'public');
            $validated['profile_photo'] = $path;
        }

        $user->update($validated);

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
