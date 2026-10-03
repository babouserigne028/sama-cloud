<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Le compte a été suspendu par un administrateur : il ne peut plus agir.
 */
final class AccountSuspended extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Ce compte est suspendu. Contactez le support de SamaCloud.');
    }

    public function errorCode(): string
    {
        return 'compte_suspendu';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Forbidden;
    }
}
