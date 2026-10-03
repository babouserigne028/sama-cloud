<?php

declare(strict_types=1);

namespace App\Application\Deployment\Events;

/**
 * Un déploiement vient d'être demandé et enregistré (état « en_attente »).
 *
 * C'est le point de branchement du moteur de déploiement : il écoute cet événement
 * et lance son travail (clonage, construction, mise en ligne). L'API, elle, ne touche
 * jamais à Docker.
 */
final readonly class DeploymentRequested
{
    public function __construct(
        public string $deploymentId,
        public string $operationId,
        public string $projectId,
    ) {}
}
