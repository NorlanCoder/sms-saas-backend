<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $admin */
        $admin = User::query()->where('email', $validated['email'])->first();

        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects',
            ], 401);
        }

        if (($admin->role ?? 'admin') !== 'super_admin') {
            return response()->json([
                'message' => 'Accès super admin requis',
            ], 403);
        }

        $admin->tokens()->delete();

        $token = $admin->createToken('super-admin-token', ['admin', 'super-admin'])->plainTextToken;

        return response()->json([
            'message' => 'Connexion super admin réussie',
            'token' => $token,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Non authentifié',
            ], 401);
        }

        $user->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ], 200);
    }
}
