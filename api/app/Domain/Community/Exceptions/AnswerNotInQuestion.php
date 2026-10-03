<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * On ne peut accepter qu'une réponse qui appartient à la question.
 */
final class AnswerNotInQuestion extends BusinessException
{
    public function __construct()
    {
        parent::__construct("Cette réponse n'appartient pas à cette question.");
    }

    public function errorCode(): string
    {
        return 'reponse_hors_question';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Invalid;
    }
}
