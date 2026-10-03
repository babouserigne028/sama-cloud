<?php

declare(strict_types=1);

namespace App\Domain\Account\Enums;

/**
 * Rôle d'un compte.
 *
 * L'« agent IA » n'est pas un rôle de compte : c'est un type de jeton
 * qui agit au nom d'un client (voir ActorType).
 */
enum UserRole: string
{
    /** Développeur qui déploie et gère ses propres projets. */
    case Client = 'client';

    /** Administrateur Systalink : catalogue, crédits, suspension de comptes. */
    case Admin = 'administrateur';
}
