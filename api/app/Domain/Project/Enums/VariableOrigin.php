<?php

declare(strict_types=1);

namespace App\Domain\Project\Enums;

/**
 * D'où vient la valeur d'une variable d'environnement (les trois formes de datacloud.yaml).
 */
enum VariableOrigin: string
{
    /** Valeur non sensible, écrite en clair dans datacloud.yaml. */
    case Plain = 'publique';

    /** Secret saisi par le client (back-office ou IA). */
    case Secret = 'secret';

    /** Secret généré par l'API au premier déploiement. */
    case Generated = 'genere';

    /**
     * Une valeur sensible ne doit jamais sortir de l'API ni apparaître dans un template.
     */
    public function isSensitive(): bool
    {
        return $this !== self::Plain;
    }
}
