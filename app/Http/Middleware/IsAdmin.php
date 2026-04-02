<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Non authentifié',
            ], 401);
        }

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        if ($user->currentAccessToken() && ! $user->tokenCan('admin')) {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        return $next($request);
    }
}
