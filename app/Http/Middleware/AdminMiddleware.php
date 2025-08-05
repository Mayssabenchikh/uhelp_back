<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle($request, Closure $next)
{
    if (Auth::check() && Auth::user()->role === 'admin') {
        return $next($request);
    }

    return response()->json(['message' => 'Unauthorized'], 403);
}

}
