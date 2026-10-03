<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

/**
 * Famille d'une erreur métier.
 *
 * Le domaine ne connaît pas HTTP : il dit seulement « de quel genre »
 * est l'erreur. C'est la couche Http qui traduit ensuite chaque famille
 * en code de statut (401, 403, 404, 409, 422).
 */
enum ErrorCategory: string
{
    /** Les données fournies ne respectent pas une règle métier. */
    case Invalid = 'invalid';

    /** L'identité n'a pas pu être prouvée (mauvais identifiants). */
    case Unauthenticated = 'unauthenticated';

    /** L'action est interdite pour cet utilisateur ou ce jeton. */
    case Forbidden = 'forbidden';

    /** La ressource demandée n'existe pas (ou n'est pas visible). */
    case NotFound = 'not_found';

    /** L'action contredit l'état actuel (doublon, quota atteint, mauvais statut). */
    case Conflict = 'conflict';
}
