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

    'xe_pay' => [
        'api_url' => env('XE_PAY_API_URL', 'https://pay.xepos.cloud/api'),
        'api_key' => env('XE_PAY_API_KEY'),
        'webhook_secret' => env('XE_PAY_WEBHOOK_SECRET'),
    ],

    'xe_ai' => [
        'api_url' => env('XE_AI_API_URL', 'https://ai.xepos.cloud/api'),
        'api_key' => env('XE_AI_API_KEY'),
    ],

    'whats_hub' => [
        'api_url' => env('WHATS_HUB_API_URL', 'https://api.whatshub.io'),
        'api_key' => env('WHATS_HUB_API_KEY'),
        'webhook_secret' => env('WHATS_HUB_WEBHOOK_SECRET'),
    ],

    'openroute' => [
        'api_key' => env('OPENROUTE_API_KEY'), // Optional - free tier available without key
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'), // Deprecated - kept for backward compatibility
    ],

    'google_calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.1-70b-versatile'),
    ],

];

