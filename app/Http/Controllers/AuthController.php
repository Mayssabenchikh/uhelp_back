<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;

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
            'role'     => 'client',
        ]);

        // event pour email verification si tu utilises MustVerifyEmail
        event(new Registered($user));

        // create personal access token (plainTextToken)
        $token = $user->createToken('auth_token')->plainTextToken;

        // send verification email (optional)
        if (method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        // retourne user sans le password
        $userSafe = $user->makeHidden(['password', 'remember_token']);

        return response()->json([
            'status'       => true,
            'message'      => 'User registered successfully',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $userSafe,
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

        if (method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'status' => false,
                'message' => 'Please verify your email address before logging in.'
            ], 403);
        }

        // Création du token API (personal access token)
        $token = $user->createToken('api-token')->plainTextToken;

        $userSafe = $user->makeHidden(['password', 'remember_token']);

        return response()->json([
            'message'      => 'Connexion réussie',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $userSafe,
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
        // supprime le token courant
        $currentToken = $request->user()->currentAccessToken();
        if ($currentToken) {
            $request->user()->tokens()->where('id', $currentToken->id)->delete();
        }

        return response()->json([
            'status'  => true,
            'message' => 'Logged out successfully'
        ]);
    }
}
