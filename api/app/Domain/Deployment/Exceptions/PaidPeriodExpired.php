<?php

declare(strict_types=1);

namespace App\Domain\Deployment\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Les redéploiements sont illimités, mais seulement pendant la période payée.
 */
final class PaidPeriodExpired extends BusinessException
{
    public function __construct()
    {
        parent::__construct('La période payée de ce projet est terminée. Renouvelez-la pour déployer à nouveau.');
    }

    public function errorCode(): string
    {
        return 'periode_expiree';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Conflict;
    }
}
