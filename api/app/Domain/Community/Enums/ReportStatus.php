<?php

declare(strict_types=1);

namespace App\Domain\Community\Enums;

/**
 * État d'un signalement.
 */
enum ReportStatus: string
{
    /** En attente de la décision d'un administrateur. */
    case Open = 'ouvert';

    /** Signalement justifié : le contenu a été retiré. */
    case Upheld = 'retenu';

    /** Signalement non justifié : le contenu reste en ligne. */
    case Dismissed = 'rejete';
}
