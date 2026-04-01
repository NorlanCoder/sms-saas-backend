<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyCountry;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $countries = Country::query()
            ->active()
            ->orderBy('nom')
            ->get([
                'id',
                'nom',
                'code_pays',
                'code_indicatif',
                'tarif_sms',
                'statut',
            ]);

        return response()->json([
            'countries' => $countries,
        ], 200);
    }

    public function active(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $countries = $company->countries()
            ->where('countries.statut', true)
            ->wherePivot('actif', true)
            ->orderBy('countries.nom')
            ->get([
                'countries.id',
                'countries.nom',
                'countries.code_pays',
                'countries.code_indicatif',
                'countries.tarif_sms',
                'countries.statut',
            ])
            ->map(fn (Country $country): array => [
                'id' => $country->id,
                'nom' => $country->nom,
                'code_pays' => $country->code_pays,
                'code_indicatif' => $country->code_indicatif,
                'tarif_sms' => $country->tarif_sms,
                'statut' => $country->statut,
                'actif' => (bool) $country->pivot->actif,
            ])
            ->values();

        return response()->json([
            'countries' => $countries,
        ], 200);
    }

    public function activate(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $validated = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
        ]);

        $country = Country::query()
            ->active()
            ->find($validated['country_id']);

        if (! $country) {
            return response()->json([
                'message' => 'Pays non disponible sur la plateforme',
            ], 404);
        }

        CompanyCountry::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'country_id' => $country->id,
            ],
            [
                'actif' => true,
            ]
        );

        return response()->json([
            'message' => 'Pays activé avec succès',
            'country' => [
                'id' => $country->id,
                'nom' => $country->nom,
                'code_pays' => $country->code_pays,
                'code_indicatif' => $country->code_indicatif,
                'tarif_sms' => $country->tarif_sms,
            ],
        ], 200);
    }

    public function deactivate(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $validated = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
        ]);

        $companyCountry = CompanyCountry::query()
            ->where('company_id', $company->id)
            ->where('country_id', $validated['country_id'])
            ->where('actif', true)
            ->first();

        if (! $companyCountry) {
            return response()->json([
                'message' => "Ce pays n'est pas activé pour votre compte",
            ], 404);
        }

        $companyCountry->update([
            'actif' => false,
        ]);

        return response()->json([
            'message' => 'Pays désactivé avec succès',
        ], 200);
    }
}
