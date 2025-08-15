<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureHasActiveSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->activeSubscription || ! $user->activeSubscription->isActive()) {
            return response()->json(['message' => 'Subscription required'], 403);
        }
        return $next($request);
    }
}
