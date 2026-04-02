<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Country;
use App\Models\SenderID;
use App\Models\SmsLog;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOperationsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sender_id_index_defaults_to_pending_and_sorted_ascending(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $admin = $this->createAdmin();
        $company = $this->createCompany('pending@example.test');

        $oldPending = SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'OLDPENDING',
            'statut' => 'en_attente',
            'created_at' => Carbon::parse('2026-04-01 09:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 09:00:00'),
        ]);

        SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'VALIDED',
            'statut' => 'valide',
            'created_at' => Carbon::parse('2026-04-01 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 10:00:00'),
        ]);

        SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'NEWPENDING',
            'statut' => 'en_attente',
            'created_at' => Carbon::parse('2026-04-01 11:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 11:00:00'),
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/admin/sender-ids');

        $response
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.id', $oldPending->id)
            ->assertJsonPath('data.0.company.email', 'pending@example.test');

        Carbon::setTestNow();
    }

    public function test_sender_id_actions_approve_reject_and_suspend(): void
    {
        $admin = $this->createAdmin();
        $company = $this->createCompany('actions@example.test');

        $sender = SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'ACTIONID',
            'statut' => 'en_attente',
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $this->putJson('/api/admin/sender-ids/'.$sender->id.'/approve')
            ->assertOk()
            ->assertJsonPath('sender_id.statut', 'valide');

        $this->putJson('/api/admin/sender-ids/'.$sender->id.'/reject', [])
            ->assertStatus(422);

        $this->putJson('/api/admin/sender-ids/'.$sender->id.'/reject', [
            'motif' => 'Nom non conforme',
        ])->assertOk()->assertJsonPath('sender_id.statut', 'rejete');

        $this->putJson('/api/admin/sender-ids/'.$sender->id.'/suspend')
            ->assertOk()
            ->assertJsonPath('sender_id.statut', 'suspendu');
    }

    public function test_country_index_and_updates_work_with_validation(): void
    {
        $admin = $this->createAdmin();

        $country = Country::query()->create([
            'nom' => 'Benin',
            'code_pays' => 'BJ',
            'code_indicatif' => '+229',
            'tarif_sms' => 0.025,
            'statut' => true,
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/admin/countries')
            ->assertOk()
            ->assertJsonPath('data.0.nom', 'Benin');

        $this->putJson('/api/admin/countries/'.$country->id.'/tariff', [
            'tarif_sms' => -1,
        ])->assertStatus(422);

        $this->putJson('/api/admin/countries/'.$country->id.'/tariff', [
            'tarif_sms' => 0.030,
        ])->assertOk()->assertJsonPath('country.tarif_sms', 0.03);

        $this->putJson('/api/admin/countries/'.$country->id.'/status', [
            'statut' => false,
        ])->assertOk()->assertJsonPath('country.statut', false);
    }

    public function test_admin_sms_logs_index_filters_by_company_status_and_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $admin = $this->createAdmin();
        $companyA = $this->createCompany('smsa@example.test');
        $companyB = $this->createCompany('smsb@example.test');
        $senderA = SenderID::query()->create([
            'company_id' => $companyA->id,
            'nom' => 'SMSSA',
            'statut' => 'valide',
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $companyA->id,
            'sender_id' => $senderA->id,
            'destinataire' => '+22996020001',
            'message' => 'Match',
            'statut' => 'envoye',
            'cout' => 0.02,
            'created_at' => Carbon::parse('2026-04-02 09:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 09:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $companyA->id,
            'sender_id' => $senderA->id,
            'destinataire' => '+22996020002',
            'message' => 'No status',
            'statut' => 'echoue',
            'cout' => 0.02,
            'created_at' => Carbon::parse('2026-04-02 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $companyB->id,
            'sender_id' => null,
            'destinataire' => '+22996020003',
            'message' => 'No company',
            'statut' => 'envoye',
            'cout' => 0.02,
            'created_at' => Carbon::parse('2026-04-02 11:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 11:00:00'),
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/admin/sms/logs?company_id='.$companyA->id.'&statut=envoye&date_debut=2026-04-02&date_fin=2026-04-02');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.company.nom', $companyA->nom)
            ->assertJsonPath('data.0.sender.nom', 'SMSSA');

        Carbon::setTestNow();
    }

    public function test_admin_transactions_index_filters_and_paginates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $admin = $this->createAdmin();
        $companyA = $this->createCompany('txa@example.test');
        $companyB = $this->createCompany('txb@example.test');

        Transaction::query()->forceCreate([
            'company_id' => $companyA->id,
            'type' => 'recharge',
            'montant' => 100,
            'description' => 'Match',
            'created_at' => Carbon::parse('2026-04-02 09:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 09:00:00'),
        ]);

        Transaction::query()->forceCreate([
            'company_id' => $companyA->id,
            'type' => 'debit',
            'montant' => 10,
            'description' => 'No type',
            'created_at' => Carbon::parse('2026-04-02 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:00:00'),
        ]);

        Transaction::query()->forceCreate([
            'company_id' => $companyB->id,
            'type' => 'recharge',
            'montant' => 50,
            'description' => 'No company',
            'created_at' => Carbon::parse('2026-04-02 11:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 11:00:00'),
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/admin/transactions?company_id='.$companyA->id.'&type=recharge&date_debut=2026-04-02&date_fin=2026-04-02');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('data.0.company.nom', $companyA->nom)
            ->assertJsonPath('data.0.type', 'recharge');

        Carbon::setTestNow();
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('Admin@1234'),
        ]);
    }

    private function createCompany(string $email): Company
    {
        $company = new Company([
            'nom' => fake()->company(),
            'email' => $email,
            'pays' => 'BJ',
            'telephone' => '+22996000000',
            'solde' => 100,
            'statut' => 'actif',
        ]);

        $company->password = 'secret';
        $company->save();

        return $company;
    }
}
