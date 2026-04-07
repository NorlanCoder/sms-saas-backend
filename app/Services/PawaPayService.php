<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class PawaPayService
{
    /**
     * @return array{provider: string, status: 'success'|'failed', reference: string|null, message: string}
     */
    public function initiateDeposit(float $amount, string $phone, ?string $sandboxStatus = null): array
    {
        $isSandbox = (bool) config('services.pawapay.sandbox', true);

        if ($isSandbox) {
            $status = $sandboxStatus === 'failed' ? 'failed' : 'success';

            return [
                'provider' => 'pawapay',
                'status' => $status,
                'reference' => $status === 'success' ? uniqid('pawapay_', true) : null,
                'message' => $status === 'success'
                    ? 'Paiement PawaPay simulé avec succès.'
                    : 'Paiement PawaPay simulé en échec.',
            ];
        }

        $apiKey = (string) config('services.pawapay.api_key', '');
        $baseUrl = rtrim((string) config('services.pawapay.base_url', ''), '/');

        if ($apiKey === '' || $baseUrl === '') {
            return [
                'provider' => 'pawapay',
                'status' => 'failed',
                'reference' => null,
                'message' => 'Configuration PawaPay manquante.',
            ];
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->post($baseUrl.'/deposits', [
                    'amount' => [
                        'value' => number_format($amount, 2, '.', ''),
                        'currency' => 'XOF',
                    ],
                    'payer' => [
                        'type' => 'MSISDN',
                        'address' => [
                            'value' => $phone,
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return [
                    'provider' => 'pawapay',
                    'status' => 'failed',
                    'reference' => null,
                    'message' => 'Paiement PawaPay refusé.',
                ];
            }

            $payload = $response->json();
            $reference = is_array($payload) && isset($payload['depositId']) && is_string($payload['depositId'])
                ? $payload['depositId']
                : uniqid('pawapay_', true);

            return [
                'provider' => 'pawapay',
                'status' => 'success',
                'reference' => $reference,
                'message' => 'Paiement PawaPay confirmé.',
            ];
        } catch (Throwable $exception) {
            return [
                'provider' => 'pawapay',
                'status' => 'failed',
                'reference' => null,
                'message' => 'Erreur PawaPay: '.$exception->getMessage(),
            ];
        }
    }
}
