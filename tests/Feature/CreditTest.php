<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Transaction;
use App\Services\FedaPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_credits_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/credits/balance')->assertStatus(401);
        $this->postJson('/api/v1/credits/recharge', [
            'montant' => 1000,
            'methode' => 'mobile_money',
            'phone' => '+221770000111',
        ])->assertStatus(401);
        $this->getJson('/api/v1/credits/transactions')->assertStatus(401);
    }

    public function test_can_get_balance(): void
    {
        $company = Company::factory()->create(['solde' => 100.00]);
        $token = $company->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/v1/credits/balance', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['solde'])
            ->assertJsonFragment(['solde' => 100]);
    }

    public function test_can_recharge_credits(): void
    {
        $company = Company::factory()->create(['solde' => 50.00]);
        $token = $company->createToken('test-token')->plainTextToken;

        $this->mock(FedaPayService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('initiateTransaction')
                ->once()
                ->andReturn([
                    'success' => true,
                    'transaction_id' => 12345,
                    'status' => 'pending',
                    'message' => 'Une demande de paiement a ete envoyee sur votre telephone. Veuillez confirmer.',
                ]);
        });

        $response = $this->postJson('/api/v1/credits/recharge', [
            'montant' => 1000,
            'methode' => 'mobile_money',
            'phone' => '+221770000111',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        $this->assertSame(50.0, (float) $company->fresh()->solde);
        $this->assertDatabaseHas('transactions', [
            'company_id' => $company->id,
            'type' => 'recharge',
            'payment_status' => 'pending',
            'payment_method' => 'mobile_money',
        ]);
    }

    public function test_recharge_returns_422_when_fedapay_initiation_fails(): void
    {
        $company = Company::factory()->create(['solde' => 200.00]);
        $token = $company->createToken('test-token')->plainTextToken;

        $this->mock(FedaPayService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('initiateTransaction')
                ->once()
                ->andReturn([
                    'success' => false,
                    'message' => 'Impossible d\'initier le paiement : erreur test',
                ]);
        });

        $response = $this->postJson('/api/v1/credits/recharge', [
            'montant' => 1000,
            'methode' => 'mobile_money',
            'phone' => '+22996000000',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Impossible d\'initier le paiement : erreur test');

        $this->assertSame(200.0, (float) $company->fresh()->solde);

        $this->assertDatabaseHas('transactions', [
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => '1000.00',
            'payment_status' => 'declined',
        ]);
    }

    public function test_transactions_history_is_paginated(): void
    {
        $company = Company::factory()->create();
        $token = $company->createToken('test-token')->plainTextToken;

        Transaction::factory()->count(25)->create([
            'company_id' => $company->id,
            'type' => 'recharge',
        ]);

        $response = $this->getJson('/api/v1/credits/transactions', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ])
            ->assertJsonPath('total', 25);
    }

    public function test_transactions_history_filters_by_type_and_date_range(): void
    {
        $company = Company::factory()->create();
        $token = $company->createToken('test-token')->plainTextToken;

        Transaction::factory()->create([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 1000,
            'created_at' => '2026-04-01 10:00:00',
            'updated_at' => '2026-04-01 10:00:00',
        ]);

        Transaction::factory()->create([
            'company_id' => $company->id,
            'type' => 'debit',
            'montant' => 500,
            'created_at' => '2026-04-02 10:00:00',
            'updated_at' => '2026-04-02 10:00:00',
        ]);

        Transaction::factory()->create([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 700,
            'created_at' => '2026-03-01 10:00:00',
            'updated_at' => '2026-03-01 10:00:00',
        ]);

        $response = $this->getJson('/api/v1/credits/transactions?type=recharge&date_debut=2026-04-01&date_fin=2026-04-30', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.type', 'recharge')
            ->assertJsonPath('data.0.montant', '1000.00');
    }

    public function test_webhook_rejects_invalid_signature_when_secret_is_configured(): void
    {
        config(['fedapay.webhook_secret' => 'test-secret']);

        $company = Company::factory()->create(['solde' => 0.00]);
        Transaction::query()->create([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 1000,
            'description' => 'Recharge test webhook',
            'fedapay_transaction_id' => '987654',
            'payment_status' => 'pending',
            'payment_method' => 'mobile_money',
            'phone' => '+22996000000',
        ]);

        $payload = [
            'name' => 'transaction.approved',
            'data' => [
                'transaction' => [
                    'id' => '987654',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-FEDAPAY-SIGNATURE' => 'invalid-signature',
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/webhooks/fedapay', $payload);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Signature webhook invalide');

        $this->assertSame(0.0, (float) $company->fresh()->solde);
    }

    public function test_webhook_approves_payment_and_credits_balance_with_valid_signature(): void
    {
        $secret = 'test-secret';
        config(['fedapay.webhook_secret' => $secret]);

        $company = Company::factory()->create(['solde' => 0.00]);
        $transaction = Transaction::query()->create([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 2500,
            'description' => 'Recharge test webhook valide',
            'fedapay_transaction_id' => '123456',
            'payment_status' => 'pending',
            'payment_method' => 'mobile_money',
            'phone' => '+22996000000',
        ]);

        $payload = [
            'name' => 'transaction.approved',
            'data' => [
                'transaction' => [
                    'id' => '123456',
                ],
            ],
        ];

        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawPayload, $secret);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/fedapay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_FEDAPAY_SIGNATURE' => $signature,
            ],
            $rawPayload
        );

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Webhook traite avec succes');

        $this->assertSame(2500.0, (float) $company->fresh()->solde);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'approved',
        ]);
    }

}
