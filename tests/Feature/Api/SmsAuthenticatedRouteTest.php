<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Country;
use App\Models\SenderID;
use App\Services\SmsProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class SmsAuthenticatedRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_sms_send_route_requires_authentication(): void
    {
        $this->postJson('/api/v1/sms/send', [
            'to' => '+221771234567',
            'message' => 'Message test',
            'sender_id' => 'MYBRAND',
        ])->assertStatus(401);
    }

    public function test_authenticated_sms_send_route_sends_sms_and_deducts_balance(): void
    {
        $company = Company::factory()->create(['solde' => 100.00]);
        $sender = SenderID::factory()->create([
            'company_id' => $company->id,
            'nom' => 'MYBRAND',
            'statut' => 'valide',
        ]);

        $country = Country::factory()->create([
            'code_pays' => 'SN',
            'code_indicatif' => '+221',
            'tarif_sms' => 25.00,
            'statut' => true,
        ]);

        DB::table('company_countries')->insert([
            'company_id' => $company->id,
            'country_id' => $country->id,
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mock(SmsProviderService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message_id' => 'provider-message-id',
                    'status' => 'envoye',
                ]);
        });

        Sanctum::actingAs($company);

        $this->postJson('/api/v1/sms/send', [
            'to' => '+221771234567',
            'message' => 'Message de test',
            'sender_id' => $sender->nom,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'SMS envoyé avec succès')
            ->assertJsonPath('statut', 'envoye')
            ->assertJsonPath('cout', 25);

        $this->assertSame(75.0, (float) $company->fresh()->solde);

        $this->assertDatabaseHas('sms_logs', [
            'company_id' => $company->id,
            'destinataire' => '+221771234567',
            'statut' => 'envoye',
            'sender_id' => $sender->id,
        ]);
    }

    public function test_authenticated_sms_send_route_returns_403_when_sender_is_not_validated(): void
    {
        $company = Company::factory()->create(['solde' => 100.00]);

        SenderID::factory()->create([
            'company_id' => $company->id,
            'nom' => 'MYBRAND',
            'statut' => 'en_attente',
        ]);

        Sanctum::actingAs($company);

        $this->postJson('/api/v1/sms/send', [
            'to' => '+221771234567',
            'message' => 'Message de test',
            'sender_id' => 'MYBRAND',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'SENDER_ID invalide ou non validé');
    }
}
