<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\SenderID;
use App\Models\SmsLog;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_companies_with_filters_and_sms_count(): void
    {
        $admin = $this->createAdmin();

        $companyA = $this->createCompany('Acme BJ', 'acme@example.test', 'BJ', 'actif');
        $companyB = $this->createCompany('Beta TG', 'beta@example.test', 'TG', 'suspendu');

        $senderA = $this->createSender($companyA, 'ACMEID');
        $senderB = $this->createSender($companyB, 'BETAID');

        SmsLog::query()->forceCreate([
            'company_id' => $companyA->id,
            'sender_id' => $senderA->id,
            'destinataire' => '+22996000001',
            'message' => 'Ok',
            'statut' => 'envoye',
            'cout' => 0.01,
            'created_at' => Carbon::parse('2026-04-01 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 10:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $companyA->id,
            'sender_id' => $senderA->id,
            'destinataire' => '+22996000002',
            'message' => 'Fail',
            'statut' => 'echoue',
            'cout' => 0.01,
            'created_at' => Carbon::parse('2026-04-01 11:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 11:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $companyB->id,
            'sender_id' => $senderB->id,
            'destinataire' => '+22890000000',
            'message' => 'Ok',
            'statut' => 'envoye',
            'cout' => 0.01,
            'created_at' => Carbon::parse('2026-04-01 12:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 12:00:00'),
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/admin/companies?statut=actif&pays=BJ&search=Acme');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $companyA->id)
            ->assertJsonPath('data.0.sms_envoyes_count', 1)
            ->assertJsonPath('per_page', 20);
    }

    public function test_show_returns_company_details_with_related_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $admin = $this->createAdmin();
        $company = $this->createCompany('Delta', 'delta@example.test', 'BJ', 'actif');
        $sender = $this->createSender($company, 'DELTAID');

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996010001',
            'message' => 'SMS 1',
            'statut' => 'envoye',
            'cout' => 1.25,
            'created_at' => Carbon::parse('2026-04-01 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 10:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996010002',
            'message' => 'SMS 2',
            'statut' => 'echoue',
            'cout' => 0.75,
            'created_at' => Carbon::parse('2026-04-01 11:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 11:00:00'),
        ]);

        for ($i = 1; $i <= 7; $i++) {
            Transaction::query()->forceCreate([
                'company_id' => $company->id,
                'type' => $i % 2 === 0 ? 'debit' : 'recharge',
                'montant' => 10 + $i,
                'description' => 'T'.$i,
                'created_at' => Carbon::parse('2026-04-02 12:00:00')->subMinutes($i),
                'updated_at' => Carbon::parse('2026-04-02 12:00:00')->subMinutes($i),
            ]);
        }

        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/admin/companies/'.$company->id);

        $response
            ->assertOk()
            ->assertJsonPath('company.id', $company->id)
            ->assertJsonPath('company.total_sms_envoyes', 1)
            ->assertJsonPath('company.total_depense', 2)
            ->assertJsonCount(1, 'company.sender_ids')
            ->assertJsonCount(5, 'company.dernieres_transactions');

        Carbon::setTestNow();
    }

    public function test_show_returns_404_when_company_not_found(): void
    {
        Sanctum::actingAs($this->createAdmin(), ['admin']);

        $this->getJson('/api/admin/companies/99999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Company introuvable');
    }

    public function test_update_status_validates_and_updates_company_status(): void
    {
        $admin = $this->createAdmin();
        $company = $this->createCompany('Omega', 'omega@example.test', 'BJ', 'actif');

        Sanctum::actingAs($admin, ['admin']);

        $this->putJson('/api/admin/companies/'.$company->id.'/status', [
            'statut' => 'invalid',
        ])->assertStatus(422);

        $response = $this->putJson('/api/admin/companies/'.$company->id.'/status', [
            'statut' => 'suspendu',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Statut mis à jour avec succès')
            ->assertJsonPath('company.statut', 'suspendu');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'statut' => 'suspendu',
        ]);
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('Admin@1234'),
        ]);
    }

    private function createCompany(string $nom, string $email, string $pays, string $statut): Company
    {
        $company = new Company([
            'nom' => $nom,
            'email' => $email,
            'pays' => $pays,
            'telephone' => '+22996000000',
            'solde' => 100,
            'statut' => $statut,
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
