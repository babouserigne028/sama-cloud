<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Anti-triche : on ne vote pas « Utile » pour sa propre réponse.
 */
final class CannotVoteForOwnAnswer extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Vous ne pouvez pas voter pour votre propre réponse.');
    }

    public function errorCode(): string
    {
        return 'vote_sur_sa_reponse';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Forbidden;
    }
}
