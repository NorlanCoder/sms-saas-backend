<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/test-logger', function (Request $request) {
            return response()->json([
                'ok' => true,
                'echo' => $request->input('name'),
            ], 201);
        })->middleware('api.logger');
    }

    public function test_api_logger_masks_sensitive_fields_and_logs_response_metadata(): void
    {
        $logPath = storage_path('logs/api-test.log');

        config([
            'logging.channels.api' => [
                'driver' => 'single',
                'path' => $logPath,
                'level' => 'info',
            ],
        ]);

        if (File::exists($logPath)) {
            File::delete($logPath);
        }

        $response = $this->postJson('/api/test-logger', [
            'name' => 'Acme',
            'password' => 'super-secret',
            'public_key' => 'PUBLIC-RAW-KEY',
            'private_key' => 'PRIVATE-RAW-KEY',
            'nested' => [
                'password' => 'nested-secret',
            ],
        ], [
            'Authorization' => 'Bearer real-token-value',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);
        $this->assertTrue(File::exists($logPath));

        $content = File::get($logPath);

        $this->assertStringContainsString('Incoming API request', $content);
        $this->assertStringContainsString('Outgoing API response', $content);
        $this->assertStringContainsString('"authorization":"Bearer ***"', $content);
        $this->assertStringContainsString('"password":"***"', $content);
        $this->assertStringContainsString('"public_key":"***"', $content);
        $this->assertStringContainsString('"private_key":"***"', $content);
        $this->assertStringContainsString('"status_code":201', $content);
        $this->assertStringContainsString('"duration_ms":', $content);

        $this->assertStringNotContainsString('super-secret', $content);
        $this->assertStringNotContainsString('nested-secret', $content);
        $this->assertStringNotContainsString('real-token-value', $content);
        $this->assertStringNotContainsString('PUBLIC-RAW-KEY', $content);
        $this->assertStringNotContainsString('PRIVATE-RAW-KEY', $content);
    }

    public function test_api_logger_handles_missing_authorization_header(): void
    {
        $logPath = storage_path('logs/api-test.log');

        config([
            'logging.channels.api' => [
                'driver' => 'single',
                'path' => $logPath,
                'level' => 'info',
            ],
        ]);

        if (File::exists($logPath)) {
            File::delete($logPath);
        }

        $response = $this->postJson('/api/test-logger', [
            'name' => 'NoAuth',
        ], [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);
        $this->assertTrue(File::exists($logPath));

        $content = File::get($logPath);

        $this->assertStringContainsString('Incoming API request', $content);
        $this->assertStringContainsString('Outgoing API response', $content);
        $this->assertStringContainsString('"authorization":null', $content);
        $this->assertStringContainsString('"status_code":201', $content);
    }
}
