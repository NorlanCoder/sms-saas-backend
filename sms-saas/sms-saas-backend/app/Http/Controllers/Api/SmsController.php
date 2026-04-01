<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendSmsRequest;
use App\Jobs\SendSmsBatchJob;
use App\Models\Company;
use App\Models\Country;
use App\Models\SenderID;
use App\Models\SmsLog;
use App\Services\CreditService;
use App\Services\SmsProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SmsController extends Controller
{
    public function __construct(
        private readonly CreditService $creditService,
        private readonly SmsProviderService $smsProviderService,
    ) {
    }

    public function send(SendSmsRequest $request): JsonResponse
    {
        $company = $request->authenticated_company;

        if (! $company instanceof Company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $validated = $request->validated();
        $senderName = strtoupper((string) $validated['sender_id']);
        $senderId = SenderID::query()
            ->where('company_id', $company->id)
            ->valide()
            ->where('nom', $senderName)
            ->first();

        if (! $senderId) {
            return response()->json([
                'message' => 'SENDER_ID invalide ou non validé',
            ], 403);
        }

        $recipients = is_array($validated['to']) ? $validated['to'] : [$validated['to']];

        foreach ($recipients as $recipient) {
            $country = $this->resolveCountryFromNumber($recipient);

            if (! $country || ! $this->isCountryActiveForCompany((int) $company->id, (int) $country->id)) {
                return response()->json([
                    'message' => "Ce pays de destination n'est pas activé pour votre compte",
                ], 403);
            }
        }

        if (is_string($validated['to'])) {
            $to = $validated['to'];
            $country = $this->resolveCountryFromNumber($to);

            if (! $country) {
                return response()->json([
                    'message' => "Ce pays de destination n'est pas activé pour votre compte",
                ], 403);
            }

            $cost = (float) $country->tarif_sms;

            if (! $this->creditService->hasSufficientBalance((int) $company->id, $cost)) {
                $exception = new InsufficientBalanceException();

                SmsLog::query()->create([
                    'company_id' => $company->id,
                    'sender_id' => $senderId->id,
                    'destinataire' => $to,
                    'message' => $validated['message'],
                    'statut' => 'echoue',
                    'cout' => 0,
                    'batch_id' => null,
                ]);

                return response()->json([
                    'message' => $exception->getMessage(),
                ], 402);
            }

            $providerResponse = $this->smsProviderService->send($to, $validated['message'], $senderName);

            if (! ($providerResponse['success'] ?? false)) {
                SmsLog::query()->create([
                    'company_id' => $company->id,
                    'sender_id' => $senderId->id,
                    'destinataire' => $to,
                    'message' => $validated['message'],
                    'statut' => 'echoue',
                    'cout' => 0,
                    'batch_id' => null,
                ]);

                return response()->json([
                    'message' => "Echec de l'envoi du SMS",
                    'statut' => 'echoue',
                    'cout' => 0,
                    'solde_restant' => $this->creditService->getBalance((int) $company->id),
                ], 200);
            }

            try {
                DB::transaction(function () use ($company, $senderId, $to, $validated, $cost): void {
                    $this->creditService->deductCredit(
                        (int) $company->id,
                        $cost,
                        'Débit SMS vers '.$to
                    );

                    SmsLog::query()->create([
                        'company_id' => $company->id,
                        'sender_id' => $senderId->id,
                        'destinataire' => $to,
                        'message' => $validated['message'],
                        'statut' => 'envoye',
                        'cout' => $cost,
                        'batch_id' => null,
                    ]);
                });
            } catch (InsufficientBalanceException $exception) {
                SmsLog::query()->create([
                    'company_id' => $company->id,
                    'sender_id' => $senderId->id,
                    'destinataire' => $to,
                    'message' => $validated['message'],
                    'statut' => 'echoue',
                    'cout' => 0,
                    'batch_id' => null,
                ]);

                return response()->json([
                    'message' => $exception->getMessage(),
                ], 402);
            } catch (Throwable) {
                SmsLog::query()->create([
                    'company_id' => $company->id,
                    'sender_id' => $senderId->id,
                    'destinataire' => $to,
                    'message' => $validated['message'],
                    'statut' => 'echoue',
                    'cout' => 0,
                    'batch_id' => null,
                ]);

                return response()->json([
                    'message' => "Echec de l'envoi du SMS",
                    'statut' => 'echoue',
                    'cout' => 0,
                    'solde_restant' => $this->creditService->getBalance((int) $company->id),
                ], 200);
            }

            return response()->json([
                'message' => 'SMS envoyé avec succès',
                'statut' => 'envoye',
                'cout' => $cost,
                'solde_restant' => $this->creditService->getBalance((int) $company->id),
            ], 200);
        }

        $batchId = uniqid('batch_', true);

        SendSmsBatchJob::dispatch(
            (int) $company->id,
            $recipients,
            $validated['message'],
            $senderName,
            $batchId
        );

        return response()->json([
            'message' => 'Envoi en masse initié',
            'batch_id' => $batchId,
            'total_destinataires' => count($recipients),
        ], 202);
    }

    public function batchStatus(Request $request, string $batchId): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $logs = SmsLog::query()
            ->where('company_id', $company->id)
            ->where('batch_id', $batchId)
            ->get();

        return response()->json([
            'batch_id' => $batchId,
            'total' => $logs->count(),
            'envoyes' => $logs->where('statut', 'envoye')->count(),
            'echoues' => $logs->where('statut', 'echoue')->count(),
            'en_attente' => $logs->where('statut', 'en_attente')->count(),
            'cout_total' => round((float) $logs->sum('cout'), 4),
        ], 200);
    }

    private function resolveCountryFromNumber(string $recipient): ?Country
    {
        $normalizedRecipient = preg_replace('/\s+/', '', $recipient) ?? $recipient;

        /** @var Country|null $country */
        $country = Country::query()
            ->active()
            ->orderByRaw('CHAR_LENGTH(code_indicatif) DESC')
            ->get()
            ->first(fn (Country $item): bool => str_starts_with($normalizedRecipient, $item->code_indicatif));

        return $country;
    }

    private function isCountryActiveForCompany(int $companyId, int $countryId): bool
    {
        return DB::table('company_countries')
            ->where('company_id', $companyId)
            ->where('country_id', $countryId)
            ->where('actif', true)
            ->exists();
    }
}
