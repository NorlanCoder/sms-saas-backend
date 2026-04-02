<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

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
}
