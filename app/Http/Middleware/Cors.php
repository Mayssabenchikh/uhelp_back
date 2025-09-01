<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle(Request $request, Closure $next)
    {
        // Allowed origins - adapte selon ton environnement (prod/dev)
        $allowed = [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            // ajoute ton domaine de prod ici
        ];

        $origin = $request->headers->get('Origin');

        // Préparer response preflight
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        // Si origin autorisé, le renvoyer explicitement (evite problèmes avec credentials)
        if ($origin && in_array($origin, $allowed, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'false'); // pas de cookies
        } else {
            // Optionnel: renvoyer wildcard si tu veux (attention en prod)
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Credentials', 'false');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, X-CSRF-TOKEN');
        // expose Authorization header client side if besoin
        $response->headers->set('Access-Control-Expose-Headers', 'Authorization');

        return $response;
    }
}
