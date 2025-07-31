<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthConroller extends Controller
{
    public function register(Request $request) {
        $data=$request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
        ]);
        User::create($data);
        return response()->json(['status' => true,'message' => 'User created successfully']);
    //
}
public function login(Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);
    //
    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json(['status' => false,'message' => 'Invalid login details']);
    }
    $user=Auth::user();
    $token=$user->createToken('mytoken')->plainTextToken;
    return response()->json(['status' => true,'message' => 'Login successfully','token' => $token]);
}
public function profile() {
    $user=Auth::user();
    return response()->json(['status' => true,'message' => 'User profile data','user' => $user]);
    //
}
public function logout() {
    //
    Auth::logout();
    return response()->json(['status' => true,'message' => 'Logout successfully']);
}
}