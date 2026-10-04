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

        // Plafond par adresse IP pour l'ensemble des appels faits avec un jeton, valide ou non.
        'requests_per_minute_per_ip' => (int) env('API_REQUESTS_PER_MINUTE_PER_IP', 300),
    ],

    'community' => [
        // Publications par minute et par compte (questions, réponses, votes), contre le spam.
        'posts_per_minute' => (int) env('COMMUNITY_POSTS_PER_MINUTE', 20),

        // Anti-triche : âge minimum (en heures) du compte qui vote ou qui accepte une réponse pour que
        // son geste rapporte des points. Empêche de créer des comptes à la chaîne pour gonfler un classement.
        'min_account_age_hours_for_points' => (int) env('COMMUNITY_MIN_ACCOUNT_AGE_HOURS', 24),
    ],

    'showcase' => [
        // Limites de la copie d'un dépôt GitHub dans la vitrine.
        'max_archive_bytes' => 30 * 1024 * 1024, // archive téléchargée depuis GitHub
        'max_files' => 500,                      // fichiers gardés
        'max_file_bytes' => 200 * 1024,          // taille d'un fichier
        'max_total_bytes' => 5 * 1024 * 1024,    // taille de tous les fichiers gardés
        'download_timeout_seconds' => 60,
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
