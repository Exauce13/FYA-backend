<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->statut !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Acces reserve aux administrateurs.',
            ], 403);
        }

        return $next($request);
    }
}
