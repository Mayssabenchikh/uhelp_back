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
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users,email',
            'password'              => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
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

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'client',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'      => true,
            'message'     => 'User registered successfully',
            'access_token'=> $token,
            'token_type'  => 'Bearer',
            'user'        => $user,
        ], 201);
    }

public function login(Request $request)
{
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Identifiants invalides'], 401);
    }

    // Création du token API
    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'message' => 'Connexion réussie',
        'access_token' => $token,
        'user' => $user,
    ], 200);
}

    public function profile()
    {
        return response()->json([
            'status' => true,
            'user'   => Auth::user()
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->where('id', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Logged out successfully'
        ]);
    }
}
