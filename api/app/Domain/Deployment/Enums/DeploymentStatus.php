<?php

declare(strict_types=1);

namespace App\Domain\Deployment\Enums;

/**
 * Étapes d'un déploiement, dans l'ordre où elles se suivent.
 */
enum DeploymentStatus: string
{
    /** Demandé, en attente du worker. */
    case Queued = 'en_attente';

    /** Clonage du dépôt et construction de l'image. */
    case Building = 'construction';

    /** Commande « release » (ex. migrations) et contrôle de santé. */
    case Releasing = 'publication';

    /** Version en ligne. */
    case Live = 'en_ligne';

    /** Échec : l'ancienne version reste en ligne. */
    case Failed = 'echec';

    /**
     * Un déploiement terminé (en ligne ou en échec) ne change plus jamais d'état.
     */
    public function isFinished(): bool
    {
        return $this === self::Live || $this === self::Failed;
    }
}
