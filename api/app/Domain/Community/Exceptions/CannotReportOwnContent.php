<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * On ne signale pas son propre contenu : on le modifie ou on le supprime.
 */
final class CannotReportOwnContent extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Vous ne pouvez pas signaler votre propre contenu. Vous pouvez le modifier ou le supprimer.');
    }

    public function errorCode(): string
    {
        return 'signalement_de_son_contenu';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Forbidden;
    }
}
