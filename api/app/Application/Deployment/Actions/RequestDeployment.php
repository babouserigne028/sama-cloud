<?php

declare(strict_types=1);

namespace App\Application\Deployment\Actions;

use App\Application\Deployment\Events\DeploymentRequested;
use App\Domain\Deployment\Enums\DeploymentStatus;
use App\Domain\Deployment\Enums\OperationStatus;
use App\Domain\Deployment\Enums\OperationType;
use App\Domain\Deployment\Exceptions\DeploymentAlreadyInProgress;
use App\Domain\Deployment\Exceptions\PaidPeriodExpired;
use App\Domain\Deployment\Exceptions\ProjectNotDeployable;
use App\Domain\Shared\Enums\ActorType;
use App\Models\Deployment;
use App\Models\Operation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre une demande de déploiement d'un projet déjà payé (« redéployer »).
 *
 * L'action ne déploie rien elle-même : elle vérifie les règles, crée le déploiement
 * et son opération de suivi, puis prévient le moteur par un événement.
 */
final class RequestDeployment
{
    /**
     * @throws ProjectNotDeployable si le projet n'est ni en ligne ni en échec.
     * @throws PaidPeriodExpired si la période payée est terminée.
     * @throws DeploymentAlreadyInProgress si un déploiement du projet n'est pas terminé.
     */
    public function handle(Project $project, User $requester, ActorType $actor, ?string $branch, ?string $commitSha): Deployment
    {
        return DB::transaction(function () use ($project, $requester, $actor, $branch, $commitSha): Deployment {
            // Le projet est verrouillé pendant les vérifications : deux demandes simultanées
            // passent l'une après l'autre, et la seconde voit le déploiement de la première.
            $project = Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            if (! $project->status->canBeDeployed()) {
                throw new ProjectNotDeployable($project->status);
            }

            if ($project->paid_until === null || $project->paid_until->isPast()) {
                throw new PaidPeriodExpired;
            }

            $running = $project->deployments()
                ->whereIn('status', array_map(
                    static fn (DeploymentStatus $status): string => $status->value,
                    DeploymentStatus::inProgress(),
                ))
                ->first();

            if ($running !== null) {
                throw new DeploymentAlreadyInProgress($running->id, $running->operation_id);
            }

            $operation = Operation::query()->create([
                'user_id' => $requester->id,
                'project_id' => $project->id,
                'type' => OperationType::Deployment,
                'status' => OperationStatus::Pending,
                'progress' => 0,
            ]);

            $deployment = Deployment::query()->create([
                'project_id' => $project->id,
                'operation_id' => $operation->id,
                'user_id' => $requester->id,
                'actor' => $actor,
                'status' => DeploymentStatus::Queued,
                'branch' => $branch ?? $project->branch,
                'commit_sha' => $commitSha,
                // Copie de la configuration utilisée : on saura toujours avec quoi cette version a été construite.
                'config' => $project->config ?? [],
            ]);

            // Le moteur n'est prévenu qu'une fois les données réellement enregistrées.
            DB::afterCommit(static fn () => event(new DeploymentRequested(
                $deployment->id,
                $operation->id,
                $project->id,
            )));

            return $deployment;
        });
    }
}
