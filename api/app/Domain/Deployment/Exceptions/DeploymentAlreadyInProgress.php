<?php

declare(strict_types=1);

namespace App\Domain\Deployment\Exceptions;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Un seul déploiement à la fois par projet. Les détails donnent le déploiement en cours,
 * pour que le client suive celui-là au lieu d'en lancer un second.
 */
final class DeploymentAlreadyInProgress extends BusinessException
{
    public function __construct(string $deploymentId, string $operationId)
    {
        parent::__construct(
            'Un déploiement est déjà en cours pour ce projet. Attendez sa fin avant d\'en lancer un autre.',
            ['deploiement_id' => $deploymentId, 'operation_id' => $operationId],
        );
    }

    public function errorCode(): string
    {
        return 'deploiement_en_cours';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Conflict;
    }
}
