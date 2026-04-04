<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->postJson('/api/v1/credits/recharge', [
            'montant' => 100,
            'methode' => 'mobile_money',
            'phone' => '+221770000111',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);

        $this->assertSame(150.0, (float) $company->fresh()->solde);
        $this->assertDatabaseHas('transactions', [
            'company_id' => $company->id,
            'type' => 'recharge',
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
}
