<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\LoginCompanyRequest;
use App\Http\Requests\RegisterCompanyRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Models\Company;
use App\Services\RsaKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AuthController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function companyPayload(Company $company): array
    {
        return [
            'id' => $company->id,
            'nom' => $company->nom,
            'email' => $company->email,
            'pays' => $company->pays,
            'telephone' => $company->telephone,
            'solde' => (float) $company->solde,
            'statut' => $company->statut,
            'created_at' => $company->created_at?->toISOString(),
        ];
    }

    public function __construct(private readonly RsaKeyService $rsaKeyService)
    {
    }

    public function register(RegisterCompanyRequest $request): JsonResponse
    {
        $companyData = $request->companyData();
        $password = $companyData['password'];
        unset($companyData['password']);

        try {
            return DB::transaction(function () use ($companyData, $password) {
                $company = new Company($companyData);
                $company->password = $password;
                $company->solde = 0;
                $company->statut = 'actif';
                $company->save();
                $keys = $this->rsaKeyService->generateKeyPair();

                DB::table('api_keys')->insert([
                    'company_id' => $company->id,
                    'public_key' => $keys['public_key'],
                    'statut' => 'active',
                    'created_at' => now(),
                ]);

                $token = $company->createToken('company-api-token')->plainTextToken;

                return response()->json([
                    'message' => 'Compte créé avec succès',
                    'token' => $token,
                    'company' => $this->companyPayload($company),
                    'private_key' => $keys['private_key'],
                ], 201);
            });
        } catch (Throwable) {
            return response()->json([
                'message' => 'Erreur lors de la creation du compte',
            ], 500);
        }
    }

    public function login(LoginCompanyRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $company = Company::query()->where('email', $credentials['email'])->first();

        if (! $company || ! Hash::check($credentials['password'], $company->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects',
            ], 401);
        }

        if ($company->statut !== 'actif') {
            return response()->json([
                'message' => 'Compte suspendu',
            ], 403);
        }

        $company->tokens()->delete();
        $token = $company->createToken('company-api-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'token' => $token,
            'company' => $this->companyPayload($company),
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ], 200);
    }

    public function profile(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $payload = $this->companyPayload($company);

        return response()->json([
            'company' => $payload,
            'data' => $payload,
        ], 200);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $company->update($request->validated());
        $company->refresh();

        $payload = $this->companyPayload($company);

        return response()->json([
            'message' => 'Profil mis à jour',
            'company' => $payload,
            'data' => $payload,
        ], 200);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $company->password)) {
            return response()->json([
                'message' => 'Mot de passe actuel incorrect',
                'errors' => [
                    'current_password' => ['Mot de passe actuel incorrect'],
                ],
            ], 401);
        }

        $company->password = Hash::make($validated['password'], ['rounds' => 12]);
        $company->save();

        $currentToken = $company->currentAccessToken();

        if ($currentToken) {
            $company->tokens()->where('id', '!=', $currentToken->id)->delete();
        }

        return response()->json([
            'message' => 'Mot de passe mis à jour avec succès',
        ], 200);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $company->tokens()->delete();

        return response()->json([
            'message' => 'Déconnexion de tous les appareils réussie',
        ], 200);
    }

    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $confirmationName = trim((string) $request->validated()['confirmation_name']);

        if (mb_strtolower($confirmationName) !== mb_strtolower(trim($company->nom))) {
            return response()->json([
                'message' => 'Le nom de confirmation est incorrect',
                'errors' => [
                    'confirmation_name' => ['Le nom de confirmation est incorrect'],
                ],
            ], 422);
        }

        $company->tokens()->delete();
        $company->delete();

        return response()->json([
            'message' => 'Compte supprimé avec succès',
        ], 200);
    }
}
