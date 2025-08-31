<?php
return [

    // Applique CORS aux chemins listés ici
'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'api/*'],

    'allowed_methods' => ['*'], // autoriser GET, POST, PUT, DELETE, OPTIONS...
    
    // autorise explicitement ton front (en dev)
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // autoriser tous les headers envoyés (incl. Authorization)

    'exposed_headers' => [],

    'max_age' => 0,

    // si tu utilises cookies/Sanctum (avec credentials) -> mettre true
    'supports_credentials' => true,
];
