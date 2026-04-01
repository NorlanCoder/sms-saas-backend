<?php

namespace App\Jobs;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Country;
use App\Models\SenderID;
use App\Models\SmsLog;
use App\Services\CreditService;
use App\Services\SmsProviderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendSmsBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $recipients
     */
    public function __construct(
        private readonly int $companyId,
        private readonly array $recipients,
        private readonly string $message,
        private readonly string $senderId,
        private readonly string $batchId,
    ) {
    }

    public function handle(CreditService $creditService, SmsProviderService $smsProviderService): void
    {
        $sender = SenderID::query()
            ->where('company_id', $this->companyId)
            ->valide()
            ->where('nom', $this->senderId)
            ->first();

        $senderDbId = $sender?->id;

        foreach ($this->recipients as $index => $recipient) {
            try {
                $country = $this->resolveCountry($recipient);

                if (! $country || ! $this->isCountryActiveForCompany((int) $country->id)) {
                    $this->logSms($recipient, $senderDbId, 'echoue', 0);
                    continue;
                }

                $cost = (float) $country->tarif_sms;

                if (! $creditService->hasSufficientBalance($this->companyId, $cost)) {
                    $this->logSms($recipient, $senderDbId, 'echoue', 0);

                    $remaining = array_slice($this->recipients, $index + 1);
                    foreach ($remaining as $remainingRecipient) {
                        $this->logSms($remainingRecipient, $senderDbId, 'echoue', 0);
                    }

                    break;
                }

                $providerResponse = $smsProviderService->send($recipient, $this->message, $this->senderId);

                if (! ($providerResponse['success'] ?? false)) {
                    $this->logSms($recipient, $senderDbId, 'echoue', 0);
                    continue;
                }

                DB::transaction(function () use ($creditService, $recipient, $senderDbId, $cost): void {
                    $creditService->deductCredit(
                        $this->companyId,
                        $cost,
                        'Débit SMS vers '.$recipient.' (batch '.$this->batchId.')'
                    );

                    SmsLog::query()->create([
                        'company_id' => $this->companyId,
                        'sender_id' => $senderDbId,
                        'destinataire' => $recipient,
                        'message' => $this->message,
                        'statut' => 'envoye',
                        'cout' => $cost,
                        'batch_id' => $this->batchId,
                    ]);
                });
            } catch (InsufficientBalanceException) {
                $this->logSms($recipient, $senderDbId, 'echoue', 0);

                $remaining = array_slice($this->recipients, $index + 1);
                foreach ($remaining as $remainingRecipient) {
                    $this->logSms($remainingRecipient, $senderDbId, 'echoue', 0);
                }

                break;
            } catch (Throwable) {
                $this->logSms($recipient, $senderDbId, 'echoue', 0);
            }
        }
    }

    private function resolveCountry(string $recipient): ?Country
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

    private function isCountryActiveForCompany(int $countryId): bool
    {
        return DB::table('company_countries')
            ->where('company_id', $this->companyId)
            ->where('country_id', $countryId)
            ->where('actif', true)
            ->exists();
    }

    private function logSms(string $recipient, ?int $senderDbId, string $status, float $cost): void
    {
        SmsLog::query()->create([
            'company_id' => $this->companyId,
            'sender_id' => $senderDbId,
            'destinataire' => $recipient,
            'message' => $this->message,
            'statut' => $status,
            'cout' => $cost,
            'batch_id' => $this->batchId,
        ]);
    }
}
