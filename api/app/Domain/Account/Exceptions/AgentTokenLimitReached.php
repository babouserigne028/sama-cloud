<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Le compte a déjà le nombre maximum de jetons IA actifs.
 */
final class AgentTokenLimitReached extends BusinessException
{
    public function __construct(int $limit)
    {
        parent::__construct(
            "Vous avez atteint la limite de {$limit} jetons IA actifs. Révoquez-en un avant d'en créer un nouveau.",
            ['limite' => $limit],
        );
    }

    public function errorCode(): string
    {
        return 'limite_jetons_atteinte';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Conflict;
    }
}
