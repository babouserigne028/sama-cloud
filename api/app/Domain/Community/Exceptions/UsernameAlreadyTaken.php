<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Deux développeurs ont choisi le même pseudo au même instant : le second est refusé.
 */
final class UsernameAlreadyTaken extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Ce pseudo est déjà pris. Choisissez-en un autre.');
    }

    public function errorCode(): string
    {
        return 'pseudo_deja_pris';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Conflict;
    }
}
