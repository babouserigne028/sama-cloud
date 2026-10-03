<?php

declare(strict_types=1);

namespace App\Domain\Community\Enums;

/**
 * Disponibilité affichée sur un profil public.
 */
enum Availability: string
{
    /** Cherche activement une mission, un emploi ou une équipe. */
    case Available = 'disponible';

    /** Pas en recherche, mais prêt à écouter une proposition. */
    case OpenToOffers = 'a_l_ecoute';

    /** Ne souhaite pas être contacté pour le moment. */
    case Unavailable = 'indisponible';
}
