<?php

namespace App\Services;

use RuntimeException;

class RsaKeyService
{
    /**
     * @return array{public_key: string, private_key: string}
     */
    public function generateKeyPair(): array
    {
        $keyResource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($keyResource === false) {
            throw new RuntimeException('Impossible de generer la paire de cles RSA.');
        }

        $privateKey = '';

        if (! openssl_pkey_export($keyResource, $privateKey)) {
            throw new RuntimeException('Impossible d\'exporter la cle privee RSA.');
        }

        $details = openssl_pkey_get_details($keyResource);

        if (! is_array($details) || ! isset($details['key'])) {
            throw new RuntimeException('Impossible d\'extraire la cle publique RSA.');
        }

        return [
            'public_key' => $details['key'],
            'private_key' => $privateKey,
        ];
    }

    public function verifySignature(string $publicKey, string $data, string $signature): bool
    {
        $result = openssl_verify($data, base64_decode($signature, true) ?: '', $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }
}
