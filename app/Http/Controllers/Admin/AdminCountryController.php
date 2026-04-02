<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCountryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $countries = Country::query()
            ->orderBy('nom')
            ->get();

        return response()->json([
            'data' => $countries,
        ], 200);
    }

    public function updateTariff(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tarif_sms' => ['required', 'numeric', 'min:0'],
        ]);

        $country = Country::query()->find($id);

        if (! $country) {
            return response()->json([
                'message' => 'Pays introuvable',
            ], 404);
        }

        $country->update([
            'tarif_sms' => $validated['tarif_sms'],
        ]);

        return response()->json([
            'message' => 'Tarif mis à jour avec succès',
            'country' => [
                'id' => $country->id,
                'nom' => $country->nom,
                'tarif_sms' => (float) $country->tarif_sms,
            ],
        ], 200);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'statut' => ['required', 'boolean'],
        ]);

        $country = Country::query()->find($id);

        if (! $country) {
            return response()->json([
                'message' => 'Pays introuvable',
            ], 404);
        }

        $country->update([
            'statut' => (bool) $validated['statut'],
        ]);

        return response()->json([
            'message' => 'Statut du pays mis à jour',
            'country' => [
                'id' => $country->id,
                'nom' => $country->nom,
                'statut' => (bool) $country->statut,
            ],
        ], 200);
    }
}
