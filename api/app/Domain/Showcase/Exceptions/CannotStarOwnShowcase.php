<?php

declare(strict_types=1);

namespace App\Domain\Showcase\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Anti-triche : pas d'étoile sur son propre projet.
 */
final class CannotStarOwnShowcase extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Vous ne pouvez pas donner une étoile à votre propre projet.');
    }

    public function errorCode(): string
    {
        return 'etoile_sur_son_projet';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Forbidden;
    }
}
