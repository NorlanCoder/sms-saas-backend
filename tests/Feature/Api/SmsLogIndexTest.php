<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\SenderID;
use App\Models\SmsLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmsLogIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/sms/logs')
            ->assertStatus(401);
    }

    public function test_index_returns_422_for_invalid_filters(): void
    {
        $company = $this->createCompany('validation-smslog@example.test');
        Sanctum::actingAs($company);

        $response = $this->getJson('/api/v1/sms/logs?statut=invalide&date_debut=2026-99-01&date_fin=2026-04-40&sender_id=abc');

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'statut',
                'date_debut',
                'date_fin',
                'sender_id',
            ]);
    }

    public function test_index_applies_filters_and_returns_paginated_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $company = $this->createCompany('company@example.test');
        $senderMain = $this->createSender($company, 'BANQUEXYZ');
        $senderOther = $this->createSender($company, 'OTHERSENDER');

        $matchingLog = SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $senderMain->id,
            'destinataire' => '+22996000099',
            'message' => 'Message cible',
            'statut' => 'envoye',
            'cout' => 0.0250,
            'created_at' => Carbon::parse('2026-04-02 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $senderMain->id,
            'destinataire' => '+22996000001',
            'message' => 'Mauvais statut',
            'statut' => 'echoue',
            'cout' => 0.0200,
            'created_at' => Carbon::parse('2026-04-02 09:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 09:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $senderOther->id,
            'destinataire' => '+22996000099',
            'message' => 'Mauvais sender',
            'statut' => 'envoye',
            'cout' => 0.0200,
            'created_at' => Carbon::parse('2026-04-02 08:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 08:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $senderMain->id,
            'destinataire' => '+22911111111',
            'message' => 'Mauvais destinataire',
            'statut' => 'envoye',
            'cout' => 0.0200,
            'created_at' => Carbon::parse('2026-04-02 07:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 07:00:00'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $senderMain->id,
            'destinataire' => '+22996000099',
            'message' => 'Hors plage date',
            'statut' => 'envoye',
            'cout' => 0.0200,
            'created_at' => Carbon::parse('2026-03-30 11:00:00'),
            'updated_at' => Carbon::parse('2026-03-30 11:00:00'),
        ]);

        $otherCompany = $this->createCompany('other@example.test');
        $otherSender = $this->createSender($otherCompany, 'OTHCOMPANY');

        SmsLog::query()->forceCreate([
            'company_id' => $otherCompany->id,
            'sender_id' => $otherSender->id,
            'destinataire' => '+22996000099',
            'message' => 'Autre compagnie',
            'statut' => 'envoye',
            'cout' => 0.0200,
            'created_at' => Carbon::parse('2026-04-02 10:30:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:30:00'),
        ]);

        Sanctum::actingAs($company);

        $response = $this->getJson('/api/v1/sms/logs?statut=envoye&date_debut=2026-04-01&date_fin=2026-04-02&sender_id='.$senderMain->id.'&destinataire=96000099');

        $response
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingLog->id)
            ->assertJsonPath('data.0.statut', 'envoye')
            ->assertJsonPath('data.0.sender.nom', 'BANQUEXYZ');

        Carbon::setTestNow();
    }

    public function test_index_uses_default_pagination_of_twenty_items(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $company = $this->createCompany('pagination@example.test');
        $sender = $this->createSender($company, 'PAGINATION');

        for ($i = 1; $i <= 25; $i++) {
            SmsLog::query()->forceCreate([
                'company_id' => $company->id,
                'sender_id' => $sender->id,
                'destinataire' => '+2299600'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'message' => 'SMS '.$i,
                'statut' => 'envoye',
                'cout' => 0.0100,
                'created_at' => Carbon::parse('2026-04-02 12:00:00')->subMinutes($i),
                'updated_at' => Carbon::parse('2026-04-02 12:00:00')->subMinutes($i),
            ]);
        }

        Sanctum::actingAs($company);

        $response = $this->getJson('/api/v1/sms/logs');

        $response
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonCount(20, 'data');

        Carbon::setTestNow();
    }

    public function test_index_returns_logs_ordered_by_created_at_desc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $company = $this->createCompany('sort@example.test');
        $sender = $this->createSender($company, 'SORTSMS');

        $oldest = SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000401',
            'message' => 'Oldest',
            'statut' => 'envoye',
            'cout' => 0.0100,
            'created_at' => Carbon::parse('2026-04-02 08:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 08:00:00'),
        ]);

        $middle = SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000402',
            'message' => 'Middle',
            'statut' => 'envoye',
            'cout' => 0.0100,
            'created_at' => Carbon::parse('2026-04-02 09:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 09:00:00'),
        ]);

        $latest = SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000403',
            'message' => 'Latest',
            'statut' => 'envoye',
            'cout' => 0.0100,
            'created_at' => Carbon::parse('2026-04-02 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:00:00'),
        ]);

        Sanctum::actingAs($company);

        $response = $this->getJson('/api/v1/sms/logs');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $oldest->id);

        Carbon::setTestNow();
    }

    public function test_index_supports_filtering_with_only_date_debut(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $company = $this->createCompany('date-boundary@example.test');
        $sender = $this->createSender($company, 'DATESMS');

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000501',
            'message' => 'Avant borne',
            'statut' => 'envoye',
            'cout' => 0.0100,
            'created_at' => Carbon::parse('2026-03-31 23:59:59'),
            'updated_at' => Carbon::parse('2026-03-31 23:59:59'),
        ]);

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000502',
            'message' => 'Dans borne',
            'statut' => 'envoye',
            'cout' => 0.0100,
            'created_at' => Carbon::parse('2026-04-01 00:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 00:00:00'),
        ]);

        Sanctum::actingAs($company);

        $response = $this->getJson('/api/v1/sms/logs?date_debut=2026-04-01');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.message', 'Dans borne');

        Carbon::setTestNow();
    }

    private function createCompany(string $email): Company
    {
        $company = new Company([
            'nom' => 'Acme Corp',
            'email' => $email,
            'pays' => 'BJ',
            'telephone' => '+22996000000',
            'solde' => 100.00,
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
