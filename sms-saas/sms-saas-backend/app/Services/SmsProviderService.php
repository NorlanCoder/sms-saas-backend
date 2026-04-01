<?php

namespace App\Services;

use GuzzleHttp\Client;
use Throwable;

class SmsProviderService
{
    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client([
            'timeout' => 10,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function send(string $to, string $message, string $senderId): array
    {
        try {
            // Stub temporaire pour faciliter le remplacement par un vrai provider HTTP.
            return [
                'success' => true,
                'message_id' => uniqid('', true),
                'status' => 'envoye',
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'status' => 'echoue',
                'error' => $exception->getMessage(),
            ];
        }
    }
}
