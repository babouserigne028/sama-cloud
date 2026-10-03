<?php

declare(strict_types=1);

namespace App\Domain\Project\Enums;

/**
 * État d'un projet. Les valeurs sont celles renvoyées par l'API (champ « statut »).
 */
enum ProjectStatus: string
{
    /** Créé, mais la commande n'est pas encore payée. */
    case PendingPayment = 'en_attente_paiement';

    /** Payé : les ressources sont en cours de création. */
    case Provisioning = 'provisionnement';

    /** En ligne. */
    case Active = 'actif';

    /** Le dernier provisionnement a échoué. */
    case Failed = 'echec';

    /** Arrêté sans effacement (fin de période payée). */
    case Stopped = 'arrete';

    /** Suppression en cours. */
    case Deleting = 'suppression';

    /**
     * Ce projet occupe-t-il une place dans le quota « projets actifs » du compte ?
     * Un projet arrêté ou en cours de suppression ne compte plus.
     */
    public function countsTowardsQuota(): bool
    {
        return match ($this) {
            self::PendingPayment, self::Provisioning, self::Active, self::Failed => true,
            self::Stopped, self::Deleting => false,
        };
    }
}
