<?php

namespace Tests\Unit;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Company;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_balance_returns_correct_amount(): void
    {
        $company = Company::factory()->create(['solde' => 100.00]);

        $balance = app(CreditService::class)->getBalance((int) $company->id);

        $this->assertSame(100.0, $balance);
    }

    public function test_has_sufficient_balance_returns_true_when_enough(): void
    {
        $company = Company::factory()->create(['solde' => 100.00]);

        $result = app(CreditService::class)->hasSufficientBalance((int) $company->id, 50.00);

        $this->assertTrue($result);
    }

    public function test_has_sufficient_balance_returns_false_when_insufficient(): void
    {
        $company = Company::factory()->create(['solde' => 10.00]);

        $result = app(CreditService::class)->hasSufficientBalance((int) $company->id, 50.00);

        $this->assertFalse($result);
    }

    public function test_deduct_credit_reduces_balance(): void
    {
        $company = Company::factory()->create(['solde' => 100.00]);

        app(CreditService::class)->deductCredit((int) $company->id, 25.00, 'Test');

        $this->assertSame(75.0, (float) $company->fresh()->solde);
        $this->assertDatabaseHas('transactions', [
            'company_id' => $company->id,
            'type' => 'debit',
            'montant' => 25.00,
        ]);
    }

    public function test_deduct_credit_throws_exception_when_insufficient(): void
    {
        $company = Company::factory()->create(['solde' => 10.00]);

        $this->expectException(InsufficientBalanceException::class);

        app(CreditService::class)->deductCredit((int) $company->id, 50.00, 'Test');
    }

    public function test_add_credit_increases_balance(): void
    {
        $company = Company::factory()->create(['solde' => 50.00]);

        app(CreditService::class)->addCredit((int) $company->id, 100.00, 'Recharge test');

        $this->assertSame(150.0, (float) $company->fresh()->solde);
        $this->assertDatabaseHas('transactions', [
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 100.00,
        ]);
    }
}
