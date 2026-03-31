<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginCompanyRequest;
use App\Http\Requests\RegisterCompanyRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Company;
use App\Services\RsaKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AuthController extends Controller
{
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
                    'company' => [
                        'id' => $company->id,
                        'nom' => $company->nom,
                        'email' => $company->email,
                        'pays' => $company->pays,
                        'telephone' => $company->telephone,
                        'solde' => $company->solde,
                        'statut' => $company->statut,
                    ],
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
            'company' => [
                'id' => $company->id,
                'nom' => $company->nom,
                'email' => $company->email,
                'pays' => $company->pays,
                'telephone' => $company->telephone,
                'solde' => $company->solde,
                'statut' => $company->statut,
            ],
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

        return response()->json([
            'company' => [
                'id' => $company->id,
                'nom' => $company->nom,
                'email' => $company->email,
                'pays' => $company->pays,
                'telephone' => $company->telephone,
                'solde' => $company->solde,
                'statut' => $company->statut,
            ],
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

        return response()->json([
            'company' => [
                'id' => $company->id,
                'nom' => $company->nom,
                'email' => $company->email,
                'pays' => $company->pays,
                'telephone' => $company->telephone,
                'solde' => $company->solde,
                'statut' => $company->statut,
            ],
        ], 200);
    }
}
