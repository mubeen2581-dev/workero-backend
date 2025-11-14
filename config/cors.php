<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'auth/*', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',  // Vite dev server
        'http://localhost:3000',  // Alternative frontend port
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
        'https://workero.xepos.co.uk',        // Production frontend (HTTPS)
        'http://workero.xepos.co.uk',         // Production frontend (HTTP)
        'http://api-workero.xepos.co.uk',     // API domain (HTTP - no SSL)
        'https://api-workero.xepos.co.uk',    // API domain (HTTPS)
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 86400,

    'supports_credentials' => true,

];

