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
    'base' => env('KONNECT_API_BASE', 'https://api.konnect.network'),
    'key'  => env('KONNECT_API_KEY'),
    'merchant_id' => env('KONNECT_MERCHANT_ID'),
    'webhook_secret' => env('KONNECT_WEBHOOK_SECRET'),
    'callback_url' => env('KONNECT_CALLBACK_URL'),
    'currency' => env('KONNECT_CURRENCY', 'TND'),
],


];
