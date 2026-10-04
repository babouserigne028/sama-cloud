<?php

declare(strict_types=1);

namespace App\Domain\Showcase\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Un seul import à la fois par projet de la vitrine.
 */
final class ImportAlreadyRunning extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Un import de ce dépôt est déjà en cours. Attendez sa fin avant d\'en relancer un.');
    }

    public function errorCode(): string
    {
        return 'import_en_cours';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Conflict;
    }
}
