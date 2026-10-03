<?php

declare(strict_types=1);

namespace App\Http\OpenApi;

use Attribute;

/**
 * Déclare, au-dessus d'une action de contrôleur, une erreur métier que cette route peut renvoyer.
 * La documentation OpenAPI la reprend automatiquement.
 *
 * Exemple : #[ApiError(409, 'limite_jetons_atteinte', 'Le compte a déjà trop de jetons IA.')]
 *
 * Les erreurs communes (401, 403, 404, 422, 429) sont ajoutées toutes seules :
 * inutile de les déclarer.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ApiError
{
    public function __construct(
        public int $status,
        public string $code,
        public string $description,
    ) {}
}
