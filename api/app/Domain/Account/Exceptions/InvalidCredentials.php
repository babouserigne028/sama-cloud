<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Connexion refusée. Le message est volontairement le même que l'adresse
 * e-mail existe ou non : on ne révèle pas quels comptes existent.
 */
final class InvalidCredentials extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Adresse e-mail ou mot de passe incorrect.');
    }

    public function errorCode(): string
    {
        return 'identifiants_invalides';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Unauthenticated;
    }
}
