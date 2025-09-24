<?php

return [

    // Appliquer CORS sur les routes API et broadcasting
    'paths' => ['api/*', 'broadcasting/auth'],

    // Autoriser toutes les méthodes HTTP
    'allowed_methods' => ['*'],

    // Autoriser uniquement ton front en dev
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ],

    'allowed_origins_patterns' => [],

    // Autoriser tous les headers, y compris Authorization
    'allowed_headers' => ['*'],

    // Pas besoin d'exposer d'en-têtes spécifiques
    'exposed_headers' => [],

    'max_age' => 0,

    // Activer les credentials pour l'authentification WebSocket
    'supports_credentials' => true,
];
