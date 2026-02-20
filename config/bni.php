<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BNI Billing (eCollection) Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials and endpoint for BNI eCollection (Virtual Account Billing).
    | All values should be set via environment variables.
    |
    */

    'billing' => [
        'client_id' => env('BNI_BILLING_CLIENT_ID'),
        'secret_key' => env('BNI_BILLING_SECRET_KEY'),
        'prefix' => env('BNI_BILLING_PREFIX'),
        'url' => env('BNI_BILLING_URL', ''),
    ],


        /*
    |--------------------------------------------------------------------------
        | BNI Payment (OGP H2H v2) Configuration
    |--------------------------------------------------------------------------
    |
        | Credentials and endpoint for BNI payment gateway (OGP H2H v2).
    | All values should be set via environment variables.
    |
    */

    'payment' => [
        'base_url' => env('BNI_PAYMENT_BASE_URL'),
        'oauth_url' => env('BNI_PAYMENT_OAUTH_URL', env('BNI_PAYMENT_BASE_URL') . '/api/oauth/token'),
        'client_id' => env('BNI_PAYMENT_CLIENT_ID'),
        'client_secret' => env('BNI_PAYMENT_CLIENT_SECRET'),
        'api_key' => env('BNI_PAYMENT_API_KEY'),
        'api_secret' => env('BNI_PAYMENT_API_SECRET'),
        'client_name' => env('BNI_PAYMENT_CLIENT_NAME'),
        'client_id_prefix' => env('BNI_PAYMENT_CLIENT_ID_PREFIX', 'IDBNI'),
        'timeout_seconds' => 30,
        'verify_ssl' => true,
    ],
];
