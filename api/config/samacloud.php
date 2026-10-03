<?php

declare(strict_types=1);

/*
 * Réglages propres à SamaCloud.
 * Les valeurs viennent du fichier .env ; le code lit toujours config('samacloud.…').
 */
return [

    'api' => [
        // Nombre maximum de requêtes par minute, par jeton (ou par adresse IP si non connecté).
        'requests_per_minute' => (int) env('API_REQUESTS_PER_MINUTE', 60),
    ],

    'auth' => [
        // Essais de connexion ou d'inscription par minute, pour une même adresse e-mail et une même adresse IP.
        'attempts_per_minute' => (int) env('AUTH_ATTEMPTS_PER_MINUTE', 5),

        // Durée de vie du jeton de session du back-office, en jours.
        'session_token_days' => (int) env('AUTH_SESSION_TOKEN_DAYS', 7),

        // Jetons IA : durée de vie par défaut et maximale, en jours.
        'agent_token_default_days' => 90,
        'agent_token_max_days' => 365,

        // Nombre maximum de jetons IA actifs par compte.
        'max_agent_tokens' => (int) env('AUTH_MAX_AGENT_TOKENS', 10),
    ],

];
