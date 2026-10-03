<?php

declare(strict_types=1);

namespace App\Domain\Deployment\Exceptions;

use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;

/**
 * Le projet n'est pas dans un état qui permet un déploiement
 * (pas encore payé, arrêté, en cours de création ou de suppression).
 */
final class ProjectNotDeployable extends BusinessException
{
    public function __construct(ProjectStatus $status)
    {
        parent::__construct(
            'Ce projet ne peut pas être déployé dans son état actuel.',
            ['statut' => $status->value],
        );
    }

    public function errorCode(): string
    {
        return 'projet_non_deployable';
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Conflict;
    }
}
