<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Inscription
    public function register(Request $request)
    {
        // 1. Validation
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users,email',
            'password'              => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            // Check if it's specifically an email uniqueness error
            if ($validator->errors()->has('email')) {
                return response()->json([
                    'status'  => false,
                    'message' => 'An account with this email already exists. Please use a different email or try logging in.',
                    'errors'  => $validator->errors(),
                ], 422);
            }
            
            return response()->json([
                'status'  => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // 2. Création de l’utilisateur
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'client',
        ]);

        // 3. Création du token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'      => true,
            'message'     => 'User registered successfully',
            'access_token'=> $token,
            'token_type'  => 'Bearer',
            'user'        => $user,
        ], 201);
    }

    // Connexion
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user  = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'      => true,
            'message'     => 'Login successful',
            'access_token'=> $token,
            'token_type'  => 'Bearer',
            'user'        => $user,
        ]);
    }

    // Profil
    public function profile()
    {
        return response()->json([
            'status' => true,
            'user'   => Auth::user()
        ]);
    }

    // Déconnexion
    public function logout(Request $request)
    {
        $request->user()->tokens()->where('id', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Logged out successfully'
        ]);
    }
}
