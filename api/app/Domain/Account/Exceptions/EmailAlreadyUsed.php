<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Deux inscriptions simultanées avec la même adresse : la seconde est refusée.
 */
final class EmailAlreadyUsed extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Un compte existe déjà avec cette adresse e-mail.');
    }

    public function errorCode(): string
    {
        return 'email_deja_utilise';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Conflict;
    }
}
