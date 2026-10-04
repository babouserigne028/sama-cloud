<?php

declare(strict_types=1);

namespace App\Domain\Showcase\Enums;

/**
 * Avancement de la copie d'un dépôt GitHub dans la vitrine.
 */
enum ImportStatus: string
{
    /** Demandé, en attente du worker. */
    case Pending = 'en_attente';

    /** Téléchargement et tri des fichiers en cours. */
    case Running = 'en_cours';

    /** Les fichiers sont disponibles. */
    case Done = 'termine';

    /** L'import a échoué (dépôt introuvable, trop volumineux…). */
    case Failed = 'echec';

    /**
     * Un import pas encore terminé empêche d'en lancer un autre.
     */
    public function isInProgress(): bool
    {
        return $this === self::Pending || $this === self::Running;
    }
}
