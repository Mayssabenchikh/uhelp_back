<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Response;

class UserController extends Controller
{
    // Lister tous les utilisateurs
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        $query = User::with('department');

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

        if ($request->filled('role') && $request->get('role') !== 'all') {
            $query->where('role', $request->get('role'));
        }

        if ($request->filled('status') && $request->get('status') !== 'all') {
            $status = strtolower($request->get('status'));
            if ($status === 'active') {
                $query->whereNotNull('email_verified_at');
            } elseif ($status === 'inactive') {
                $query->whereNull('email_verified_at');
            }
        }

        if ($request->filled('department') && $request->get('department') !== 'all') {
            $query->where('department_id', $request->get('department'));
        }

        if ($request->filled('has_phone')) {
            $query->whereNotNull('phone_number')->where('phone_number', '!=', '');
        }

        if ($request->filled('has_photo')) {
            $query->whereNotNull('profile_photo')->where('profile_photo', '!=', '');
        }

        $query->withCount([
            'createdTickets',
            'resolvedTickets',
        ]);

        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return UserResource::collection($users);
    }

    // Afficher un utilisateur spécifique
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    // Créer un nouvel utilisateur
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:6',
            'role'          => ['required', Rule::in(['super_admin', 'admin', 'agent', 'client'])],
            'profile_photo' => 'nullable|image|max:2048',
            'phone_number'  => 'nullable|string|max:20',
            'location'      => 'nullable|string|max:255',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile_photos', 'public');
            $validated['profile_photo'] = $path;
        }

        $user = User::create($validated);
        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    // Mettre à jour un utilisateur
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',

    'profile_photo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',

        ]);

        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('profile_photos', $filename, 'public');

            $user->profile_photo = 'profile_photos/' . $filename;
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        $user->save();

        // Retourne l'utilisateur avec l'accesseur déjà inclus
        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    // Supprimer un utilisateur
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // Export CSV
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
            fputcsv($file, ['ID', 'Name', 'Email', 'Role', 'Phone', 'Department ID', 'Created At']);
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

    // Utilisateur connecté
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }
}
