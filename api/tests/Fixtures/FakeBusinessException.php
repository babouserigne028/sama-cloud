<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Erreur métier factice, utilisée seulement par les tests pour vérifier
 * comment l'API affiche n'importe quelle erreur métier.
 */
final class FakeBusinessException extends BusinessException
{
    /**
     * @param  array<int|string, mixed>  $details
     */
    public function __construct(
        private readonly ErrorCategory $category,
        string $message = 'Le quota de projets est atteint.',
        array $details = [],
    ) {
        parent::__construct($message, $details);
    }

    public function errorCode(): string
    {
        return 'erreur_factice';
    }

    public function category(): ErrorCategory
    {
        return $this->category;
    }
}
