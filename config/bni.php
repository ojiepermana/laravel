<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BNI eCollection API Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials and endpoint for the BNI eCollection API.
    | All values should be set via environment variables.
    |
    */

    'client_id'  => env('BNI_CLIENT_ID'),
    'secret_key' => env('BNI_SECRET_KEY'),
    'prefix'     => env('BNI_PREFIX'),
    'url'        => env('BNI_ECOLLECTION_URL', ''),
];
