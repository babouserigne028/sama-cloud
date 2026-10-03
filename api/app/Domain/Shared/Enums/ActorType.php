<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Qui a fait l'action ? Sert au journal d'audit et à l'historique des déploiements.
 */
enum ActorType: string
{
    /** Un humain, depuis le back-office. */
    case Human = 'humain';

    /** L'IA, avec un jeton « agent IA » (serveur MCP). */
    case Ai = 'ia';

    /** La plateforme elle-même (tâche planifiée, webhook de paiement…). */
    case System = 'systeme';
}
