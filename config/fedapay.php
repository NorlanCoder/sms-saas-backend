<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FedaPay Secret Key
    |--------------------------------------------------------------------------
    | Votre cle secrete FedaPay. En sandbox : commence par "sk_sandbox_"
    | En production : commence par "sk_live_"
    */
    'secret_key' => env('FEDAPAY_SECRET_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | FedaPay Public Key
    |--------------------------------------------------------------------------
    | Votre cle publique FedaPay. En sandbox : commence par "pk_sandbox_"
    | En production : commence par "pk_live_"
    */
    'public_key' => env('FEDAPAY_PUBLIC_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Environnement
    |--------------------------------------------------------------------------
    | "sandbox" pour les tests, "live" pour la production
    */
    'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | URL de callback (Webhook)
    |--------------------------------------------------------------------------
    | URL que FedaPay appellera pour notifier du statut du paiement
    */
    'callback_url' => env('FEDAPAY_CALLBACK_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Secret de signature Webhook
    |--------------------------------------------------------------------------
    | Secret partage pour verifier la signature HMAC des callbacks FedaPay.
    */
    'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Devise
    |--------------------------------------------------------------------------
    */
    'currency' => 'XOF',
];
