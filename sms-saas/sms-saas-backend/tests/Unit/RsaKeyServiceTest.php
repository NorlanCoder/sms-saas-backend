<?php

namespace Tests\Unit;

use App\Services\RsaKeyService;
use Tests\TestCase;

class RsaKeyServiceTest extends TestCase
{
    public function test_generate_key_pair_returns_public_and_private_keys(): void
    {
        $service = app(RsaKeyService::class);

        $keys = $service->generateKeyPair();

        $this->assertArrayHasKey('public_key', $keys);
        $this->assertArrayHasKey('private_key', $keys);
        $this->assertNotEmpty($keys['public_key']);
        $this->assertNotEmpty($keys['private_key']);
        $this->assertStringStartsWith('-----BEGIN PUBLIC KEY-----', $keys['public_key']);

        $startsWithPrivateHeader = str_starts_with($keys['private_key'], '-----BEGIN PRIVATE KEY-----')
            || str_starts_with($keys['private_key'], '-----BEGIN RSA PRIVATE KEY-----');

        $this->assertTrue($startsWithPrivateHeader);
    }

    public function test_verify_signature_returns_true_for_valid_signature(): void
    {
        $service = app(RsaKeyService::class);
        $keys = $service->generateKeyPair();
        $data = 'payload-to-sign';

        $result = openssl_sign($data, $rawSignature, $keys['private_key'], OPENSSL_ALGO_SHA256);

        $this->assertTrue($result);

        $isValid = $service->verifySignature($keys['public_key'], $data, base64_encode($rawSignature));

        $this->assertTrue($isValid);
    }

    public function test_verify_signature_returns_false_for_invalid_signature(): void
    {
        $service = app(RsaKeyService::class);
        $keys = $service->generateKeyPair();

        $isValid = $service->verifySignature(
            $keys['public_key'],
            'payload-to-sign',
            base64_encode('invalid-signature')
        );

        $this->assertFalse($isValid);
    }
}
