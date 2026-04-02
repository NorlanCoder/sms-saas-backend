<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\Country;
use App\Models\SenderID;
use App\Services\RsaKeyService;
use App\Services\SmsProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class SmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_sms_requires_valid_signature(): void
    {
        $response = $this->postJson('/api/v1/send-sms', [
            'to' => '+221771234567',
            'message' => 'Test message',
            'sender_id' => 'MYBRAND',
        ]);

        $response->assertStatus(401);
    }

    public function test_send_sms_fails_with_insufficient_balance(): void
    {
        $company = Company::factory()->create(['solde' => 0.00]);
        [$headers, $senderName] = $this->prepareSignedSmsHeaders($company);

        $response = $this->postJson('/api/v1/send-sms', [
            'to' => '+221771234567',
            'message' => 'Message test',
            'sender_id' => $senderName,
        ], $headers);

        $response->assertStatus(402);
    }

    public function test_send_sms_creates_log_entry(): void
    {
        $company = Company::factory()->create(['solde' => 100.00]);
        [$headers, $senderName] = $this->prepareSignedSmsHeaders($company, 25.00);

        $this->mock(SmsProviderService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message_id' => 'provider-message-id',
                    'status' => 'envoye',
                ]);
        });

        $response = $this->postJson('/api/v1/send-sms', [
            'to' => '+221771234567',
            'message' => 'Message de test',
            'sender_id' => $senderName,
        ], $headers);

        $response->assertStatus(200);

        $this->assertDatabaseHas('sms_logs', [
            'company_id' => $company->id,
            'destinataire' => '+221771234567',
            'statut' => 'envoye',
        ]);

        $this->assertSame(75.0, (float) $company->fresh()->solde);
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private function prepareSignedSmsHeaders(Company $company, float $tarif = 1.00): array
    {
        $keys = app(RsaKeyService::class)->generateKeyPair();
        ApiKey::query()->create([
            'company_id' => $company->id,
            'public_key' => $keys['public_key'],
            'statut' => 'active',
        ]);

        $senderName = 'MYBRAND';
        SenderID::factory()->create([
            'company_id' => $company->id,
            'nom' => $senderName,
            'statut' => 'valide',
        ]);

        $country = Country::factory()->create([
            'code_pays' => 'SN',
            'code_indicatif' => '+221',
            'tarif_sms' => $tarif,
            'statut' => true,
        ]);

        DB::table('company_countries')->insert([
            'company_id' => $company->id,
            'country_id' => $country->id,
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = url('/api/v1/send-sms');
        $timestamp = (string) now()->timestamp;
        $payload = 'POST'.$url.$timestamp;
        $signed = openssl_sign($payload, $rawSignature, $keys['private_key'], OPENSSL_ALGO_SHA256);

        $this->assertTrue($signed);

        return [[
            'Accept' => 'application/json',
            'X-Public-Key' => $keys['public_key'],
            'X-Signature' => base64_encode($rawSignature),
            'X-Timestamp' => $timestamp,
        ], $senderName];
    }
}
