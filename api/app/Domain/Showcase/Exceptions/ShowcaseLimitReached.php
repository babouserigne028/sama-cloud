<?php

declare(strict_types=1);

namespace App\Domain\Showcase\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Le projet a atteint le nombre de fichiers ou la taille totale autorisés dans la vitrine.
 */
final class ShowcaseLimitReached extends BusinessException
{
    public function __construct(int $maxFiles, int $maxTotalBytes)
    {
        parent::__construct(
            'Ce projet a atteint la limite de la vitrine. Supprimez des fichiers avant d\'en ajouter.',
            ['max_fichiers' => $maxFiles, 'max_taille_octets' => $maxTotalBytes],
        );
    }

    public function errorCode(): string
    {
        return 'limite_vitrine_atteinte';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Conflict;
    }
}
