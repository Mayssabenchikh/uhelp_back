<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // suppose que User a un champ "role" = 'admin'|'agent'|'client'
        if ($user->role !== $role) {
            return response()->json(['message' => 'Forbidden - role'], 403);
        }

        return $next($request);
    }
}
