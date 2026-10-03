<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Un signalement ne se traite qu'une fois.
 */
final class ReportAlreadyHandled extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Ce signalement a déjà été traité.');
    }

    public function errorCode(): string
    {
        return 'signalement_deja_traite';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Conflict;
    }
}
