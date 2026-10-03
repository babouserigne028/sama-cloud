<?php

declare(strict_types=1);

/*
 * Hachage des mots de passe.
 * Argon2id est l'algorithme recommandé aujourd'hui : il résiste aux attaques par carte graphique.
 * Les autres réglages gardent les valeurs par défaut de Laravel.
 */
return [

    'driver' => env('HASH_DRIVER', 'argon2id'),

];
