<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ], 
    'konnect' => [
        'api_base' => env('KONNECT_API_BASE', 'https://api.sandbox.konnect.network'),
        'api_key' => env('KONNECT_API_KEY'),
        'wallet_id' => env('KONNECT_WALLET_ID'),
        'callback_url' => env('KONNECT_CALLBACK_URL'),
    ],
'gemini' => [
        // Lire depuis .env — laisse des valeurs par défaut vides pour éviter les warnings
        'key'   => env('GEMINI_API_KEY', ''),
        'url'   => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta2'),
        'model' => env('GEMINI_MODEL', 'models/text-bison-001'),
    ],

];
