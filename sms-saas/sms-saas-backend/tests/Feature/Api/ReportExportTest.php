<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\SenderID;
use App\Models\SmsLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_requires_authentication(): void
    {
        $this->getJson('/api/v1/reports/export')
            ->assertStatus(401);
    }

    public function test_export_returns_422_for_invalid_filters(): void
    {
        $company = $this->createCompany('validation-report@example.test');
        Sanctum::actingAs($company);

        $response = $this->getJson('/api/v1/reports/export?statut=invalid&date_debut=2026-13-10&date_fin=2026-02-30&sender_id=foo');

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'statut',
                'date_debut',
                'date_fin',
                'sender_id',
            ]);
    }

    public function test_export_streams_csv_with_expected_headers_and_filtered_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $company = $this->createCompany('report@example.test');
        $sender = $this->createSender($company, 'BANQUEXYZ');

        $includedLog = SmsLog::query()->create([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000000',
            'message' => 'Bonjour client',
            'statut' => 'envoye',
            'cout' => 0.0250,
            'created_at' => Carbon::parse('2026-04-02 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:00:00'),
        ]);

        SmsLog::query()->create([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22997000000',
            'message' => 'Ne doit pas apparaitre',
            'statut' => 'echoue',
            'cout' => 0.0250,
            'created_at' => Carbon::parse('2026-04-02 10:30:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:30:00'),
        ]);

        $otherCompany = $this->createCompany('report-other@example.test');
        $otherSender = $this->createSender($otherCompany, 'OTHERSMS');

        SmsLog::query()->create([
            'company_id' => $otherCompany->id,
            'sender_id' => $otherSender->id,
            'destinataire' => '+22996000000',
            'message' => 'Autre compagnie',
            'statut' => 'envoye',
            'cout' => 0.0250,
            'created_at' => Carbon::parse('2026-04-02 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:00:00'),
        ]);

        Sanctum::actingAs($company);

        $response = $this->get('/api/v1/reports/export?statut=envoye&date_debut=2026-04-01&date_fin=2026-04-02&sender_id='.$sender->id.'&destinataire=96000000');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $disposition = $response->headers->get('content-disposition', '');
        $this->assertStringContainsString('attachment; filename=sms-export-2026-04-02.csv', $disposition);

        $csvContent = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csvContent))));

        $this->assertCount(2, $lines);

        $header = str_getcsv($lines[0]);
        $data = str_getcsv($lines[1]);

        $this->assertSame(['ID', 'Destinataire', 'Message', 'Sender ID', 'Statut', 'Cout', 'Date'], $header);
        $this->assertSame((string) $includedLog->id, $data[0]);
        $this->assertSame('+22996000000', $data[1]);
        $this->assertSame('Bonjour client', $data[2]);
        $this->assertSame('BANQUEXYZ', $data[3]);
        $this->assertSame('envoye', $data[4]);

        Carbon::setTestNow();
    }

    public function test_export_returns_csv_header_only_when_no_logs_match(): void
    {
        $company = $this->createCompany('empty-report@example.test');
        Sanctum::actingAs($company);

        $response = $this->get('/api/v1/reports/export?statut=envoye&destinataire=00000000');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csvContent = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csvContent))));

        $this->assertCount(1, $lines);
        $this->assertSame(
            ['ID', 'Destinataire', 'Message', 'Sender ID', 'Statut', 'Cout', 'Date'],
            str_getcsv($lines[0])
        );
    }

    public function test_export_uses_na_when_sender_relation_is_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $company = $this->createCompany('na-sender-report@example.test');

        SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => null,
            'destinataire' => '+22996000601',
            'message' => 'Sans sender',
            'statut' => 'envoye',
            'cout' => 0.0150,
            'created_at' => Carbon::parse('2026-04-02 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:00:00'),
        ]);

        Sanctum::actingAs($company);

        $response = $this->get('/api/v1/reports/export');
        $response->assertOk();

        $csvContent = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csvContent))));

        $this->assertCount(2, $lines);
        $data = str_getcsv($lines[1]);
        $this->assertSame('N/A', $data[3]);

        Carbon::setTestNow();
    }

    public function test_export_returns_rows_ordered_by_created_at_desc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $company = $this->createCompany('order-report@example.test');
        $sender = $this->createSender($company, 'ORDERCSV');

        $oldest = SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000701',
            'message' => 'Oldest row',
            'statut' => 'envoye',
            'cout' => 0.0100,
            'created_at' => Carbon::parse('2026-04-02 08:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 08:00:00'),
        ]);

        $latest = SmsLog::query()->forceCreate([
            'company_id' => $company->id,
            'sender_id' => $sender->id,
            'destinataire' => '+22996000702',
            'message' => 'Latest row',
            'statut' => 'envoye',
            'cout' => 0.0100,
            'created_at' => Carbon::parse('2026-04-02 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:00:00'),
        ]);

        Sanctum::actingAs($company);

        $response = $this->get('/api/v1/reports/export');
        $response->assertOk();

        $csvContent = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csvContent))));

        $firstDataRow = str_getcsv($lines[1]);
        $secondDataRow = str_getcsv($lines[2]);

        $this->assertSame((string) $latest->id, $firstDataRow[0]);
        $this->assertSame((string) $oldest->id, $secondDataRow[0]);

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
