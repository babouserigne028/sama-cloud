<?php

declare(strict_types=1);

namespace App\Application\Account\Data;

use App\Models\PersonalAccessToken;

/**
 * Résultat de la création d'un jeton : l'enregistrement en base et la valeur en clair.
 *
 * La valeur en clair n'existe qu'ici, le temps de la montrer UNE SEULE FOIS
 * à l'utilisateur. Elle n'est jamais stockée ni journalisée.
 */
final readonly class IssuedToken
{
    public function __construct(
        public PersonalAccessToken $token,
        public string $plainTextToken,
    ) {}
}
