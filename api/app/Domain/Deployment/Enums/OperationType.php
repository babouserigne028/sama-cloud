<?php

declare(strict_types=1);

namespace App\Domain\Deployment\Enums;

/**
 * Nature d'une opération longue, suivie par GET /api/operations/{id}.
 */
enum OperationType: string
{
    case Deployment = 'deploiement';
    case ProjectDeletion = 'suppression_projet';
    case DatabaseCreation = 'creation_base';
    case DatabaseDeletion = 'suppression_base';
}
