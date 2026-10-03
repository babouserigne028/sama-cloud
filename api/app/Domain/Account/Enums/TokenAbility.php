<?php

declare(strict_types=1);

namespace App\Domain\Account\Enums;

/**
 * Capacités d'un jeton : ce qu'il a le droit de faire.
 * Les valeurs sont celles du contrat avec le serveur MCP.
 */
enum TokenAbility: string
{
    case ProjectsRead = 'projets:lire';
    case ProjectsWrite = 'projets:ecrire';
    case ProjectsDelete = 'projets:supprimer';
    case ExchangePublish = 'echange:publier';

    /** Modifier son profil public : réservé à la session du navigateur. */
    case ManageProfile = 'profil:modifier';

    /** Créer, lister et révoquer des jetons : réservé à la session du navigateur. */
    case ManageTokens = 'jetons:gerer';

    /**
     * Capacités données à un jeton selon son type.
     * Un jeton d'agent IA ne reçoit jamais la gestion des jetons ni celle du profil.
     *
     * @return list<self>
     */
    public static function forKind(TokenKind $kind): array
    {
        $agentAbilities = [
            self::ProjectsRead,
            self::ProjectsWrite,
            self::ProjectsDelete,
            self::ExchangePublish,
        ];

        return match ($kind) {
            TokenKind::Agent => $agentAbilities,
            TokenKind::Session => [...$agentAbilities, self::ManageProfile, self::ManageTokens],
        };
    }
}
