<?php

declare(strict_types=1);

namespace App\Domain\Deployment\Enums;

/**
 * Avancement d'une opération longue.
 */
enum OperationStatus: string
{
    /** Dans la file d'attente, pas encore commencée. */
    case Pending = 'en_attente';

    case Running = 'en_cours';

    case Succeeded = 'reussie';

    case Failed = 'echouee';

    /**
     * Une opération terminée (réussie ou échouée) ne change plus jamais d'état.
     */
    public function isFinished(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
