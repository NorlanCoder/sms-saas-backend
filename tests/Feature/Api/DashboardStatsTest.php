<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\SenderID;
use App\Models\SmsLog;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/stats')
            ->assertStatus(401);
    }

    public function test_stats_returns_422_for_invalid_periode_value(): void
    {
        $company = $this->createCompany(100.00, 'validation-dashboard@example.test');
        Sanctum::actingAs($company);

        $response = $this->getJson('/api/v1/dashboard/stats?periode=annee');

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['periode']);
    }

    public function test_stats_returns_expected_month_aggregates_for_authenticated_company(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $company = $this->createCompany(146.25);
        $sender = $this->createSender($company, 'BANQUEXYZ');

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000001',
            'message' => 'SMS 1',
            'statut' => 'envoye',
            'cout' => 1.2500,
            'created_at' => Carbon::parse('2026-04-01 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 10:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000002',
            'message' => 'SMS 2',
            'statut' => 'echoue',
            'cout' => 0.5000,
            'created_at' => Carbon::parse('2026-04-02 09:30:00'),
            'updated_at' => Carbon::parse('2026-04-02 09:30:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000003',
            'message' => 'SMS 3',
            'statut' => 'en_attente',
            'cout' => 0.2500,
            'created_at' => Carbon::parse('2026-04-02 11:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 11:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000004',
            'message' => 'Ancien SMS hors periode',
            'statut' => 'envoye',
            'cout' => 5.0000,
            'created_at' => Carbon::parse('2026-03-20 08:00:00'),
            'updated_at' => Carbon::parse('2026-03-20 08:00:00'),
        ]);

        $otherCompany = $this->createCompany(50.00, 'other@example.test');
        $otherSender = $this->createSender($otherCompany, 'OTHERSMS');

        SmsLog::query()->forceCreate([
            'company_id' => $otherCompany->id,
            'sender_id' => $otherSender->id,
            'destinataire' => '+22997000000',
            'message' => 'Autre compagnie',
            'statut' => 'envoye',
            'cout' => 10.0000,
            'created_at' => Carbon::parse('2026-04-02 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:00:00'),
        ]);

        Transaction::query()->forceCreate([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 150.00,
            'description' => 'Recharge 1',
            'created_at' => Carbon::parse('2026-04-01 07:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 07:00:00'),
        ]);

        Transaction::query()->forceCreate([
            'company_id' => $company->id,
            'type' => 'debit',
            'montant' => 2.00,
            'description' => 'Debit',
            'created_at' => Carbon::parse('2026-04-02 07:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 07:00:00'),
        ]);

        Transaction::query()->forceCreate([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 40.00,
            'description' => 'Ancienne recharge hors periode',
            'created_at' => Carbon::parse('2026-03-10 07:00:00'),
            'updated_at' => Carbon::parse('2026-03-10 07:00:00'),
        ]);

        Sanctum::actingAs($company);

        $response = $this->getJson('/api/v1/dashboard/stats?periode=mois');

        $response
            ->assertOk()
            ->assertJsonPath('periode', 'mois')
            ->assertJsonPath('date_debut', '2026-04-01')
            ->assertJsonPath('date_fin', '2026-04-02')
            ->assertJsonPath('stats.total_sms', 3)
            ->assertJsonPath('stats.sms_envoyes', 1)
            ->assertJsonPath('stats.sms_echoues', 1)
            ->assertJsonPath('stats.sms_en_attente', 1)
            ->assertJsonPath('stats.depenses_totales', 2)
            ->assertJsonPath('stats.solde_actuel', 146.25)
            ->assertJsonPath('stats.recharges_totales', 150);

        Carbon::setTestNow();
    }

    public function test_stats_uses_month_as_default_when_periode_is_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-18 12:00:00'));

        $company = $this->createCompany(75.50, 'default-period@example.test');
        $sender = $this->createSender($company, 'DEFAULTS');

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000111',
            'message' => 'Mois courant',
            'statut' => 'envoye',
            'cout' => 0.5000,
            'created_at' => Carbon::parse('2026-04-10 08:00:00'),
            'updated_at' => Carbon::parse('2026-04-10 08:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000112',
            'message' => 'Mois precedent',
            'statut' => 'envoye',
            'cout' => 0.5000,
            'created_at' => Carbon::parse('2026-03-28 08:00:00'),
            'updated_at' => Carbon::parse('2026-03-28 08:00:00'),
        ]);

        Transaction::query()->forceCreate([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 20.00,
            'description' => 'Recharge avril',
            'created_at' => Carbon::parse('2026-04-12 12:00:00'),
            'updated_at' => Carbon::parse('2026-04-12 12:00:00'),
        ]);

        Sanctum::actingAs($company);

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response
            ->assertOk()
            ->assertJsonPath('periode', 'mois')
            ->assertJsonPath('date_debut', '2026-04-01')
            ->assertJsonPath('stats.total_sms', 1)
            ->assertJsonPath('stats.sms_envoyes', 1)
            ->assertJsonPath('stats.recharges_totales', 20);

        Carbon::setTestNow();
    }

    public function test_stats_filters_records_for_today_period_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 14:00:00'));

        $company = $this->createCompany(10.00, 'today-period@example.test');
        $sender = $this->createSender($company, 'TODAYSMS');

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000201',
            'message' => 'Aujourd hui envoye',
            'statut' => 'envoye',
            'cout' => 0.3000,
            'created_at' => Carbon::parse('2026-04-02 01:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 01:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000202',
            'message' => 'Hier echoue',
            'statut' => 'echoue',
            'cout' => 0.6000,
            'created_at' => Carbon::parse('2026-04-01 23:59:59'),
            'updated_at' => Carbon::parse('2026-04-01 23:59:59'),
        ]);

        Transaction::query()->forceCreate([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 5.00,
            'description' => 'Recharge du jour',
            'created_at' => Carbon::parse('2026-04-02 08:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 08:00:00'),
        ]);

        Transaction::query()->forceCreate([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 7.00,
            'description' => 'Recharge hors jour',
            'created_at' => Carbon::parse('2026-04-01 20:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 20:00:00'),
        ]);

        Sanctum::actingAs($company);

        $response = $this->getJson('/api/v1/dashboard/stats?periode=aujourd_hui');

        $response
            ->assertOk()
            ->assertJsonPath('periode', 'aujourd_hui')
            ->assertJsonPath('date_debut', '2026-04-02')
            ->assertJsonPath('date_fin', '2026-04-02')
            ->assertJsonPath('stats.total_sms', 1)
            ->assertJsonPath('stats.sms_envoyes', 1)
            ->assertJsonPath('stats.sms_echoues', 0)
            ->assertJsonPath('stats.depenses_totales', 0.3)
            ->assertJsonPath('stats.recharges_totales', 5);

        Carbon::setTestNow();
    }

    public function test_stats_filters_records_for_week_period_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $company = $this->createCompany(20.00, 'week-period@example.test');
        $sender = $this->createSender($company, 'WEEKSMS');

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000301',
            'message' => 'Lundi',
            'statut' => 'envoye',
            'cout' => 1.0000,
            'created_at' => Carbon::parse('2026-03-30 08:00:00'),
            'updated_at' => Carbon::parse('2026-03-30 08:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000302',
            'message' => 'Dimanche precedent',
            'statut' => 'envoye',
            'cout' => 1.0000,
            'created_at' => Carbon::parse('2026-03-29 12:00:00'),
            'updated_at' => Carbon::parse('2026-03-29 12:00:00'),
        ]);

        Transaction::query()->forceCreate([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 12.00,
            'description' => 'Semaine courante',
            'created_at' => Carbon::parse('2026-03-31 09:00:00'),
            'updated_at' => Carbon::parse('2026-03-31 09:00:00'),
        ]);

        Transaction::query()->forceCreate([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => 9.00,
            'description' => 'Semaine precedente',
            'created_at' => Carbon::parse('2026-03-29 08:00:00'),
            'updated_at' => Carbon::parse('2026-03-29 08:00:00'),
        ]);

        Sanctum::actingAs($company);

        $response = $this->getJson('/api/v1/dashboard/stats?periode=semaine');

        $response
            ->assertOk()
            ->assertJsonPath('periode', 'semaine')
            ->assertJsonPath('date_debut', '2026-03-30')
            ->assertJsonPath('date_fin', '2026-04-02')
            ->assertJsonPath('stats.total_sms', 1)
            ->assertJsonPath('stats.depenses_totales', 1)
            ->assertJsonPath('stats.recharges_totales', 12);

        Carbon::setTestNow();
    }

    private function createCompany(float $solde, string $email = 'company@example.test'): Company
    {
        $company = new Company([
            'nom' => 'Acme Corp',
            'email' => $email,
            'pays' => 'BJ',
            'telephone' => '+22996000000',
            'solde' => $solde,
            'statut' => 'actif',
        ]);

        $company->password = 'secret';
        $company->save();

        return $company;
    }

    private function createSender(Company $company, string $nom): SenderID
    {
        return SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => $nom,
            'statut' => 'valide',
        ]);
    }
}
