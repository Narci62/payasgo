<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->tokenCan('device:*')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Mettre à jour la dernière activité
        $request->user()->update(['last_seen_at' => now()]);

        return $next($request);
    }
}
