<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Deployment\Actions\RequestDeployment;
use App\Http\Controllers\Concerns\FindsOwnedProject;
use App\Http\OpenApi\ApiError;
use App\Http\Requests\StoreDeploymentRequest;
use App\Http\Resources\DeploymentResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Déploiements', weight: 4)]
final class DeploymentController extends Controller
{
    use FindsOwnedProject;

    /**
     * Historique des déploiements d'un projet.
     *
     * Du plus récent au plus ancien, 20 par page. Chaque déploiement indique
     * s'il a été demandé par un humain ou par l'IA.
     *
     * @param  string  $projet  Nom du projet (ex. mon-blog).
     */
    public function index(#[CurrentUser] User $user, string $projet): AnonymousResourceCollection
    {
        $deployments = $this->findOwnedProject($user, $projet)
            ->deployments()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return DeploymentResource::collection($deployments);
    }

    /**
     * Lancer un déploiement.
     *
     * Redéploie un projet pendant sa période payée. La réponse est immédiate (202) :
     * le travail se fait en arrière-plan, et « operation_id » permet d'en suivre l'avancement
     * avec GET /api/operations/{id}. Un seul déploiement à la fois par projet.
     *
     * @param  string  $projet  Nom du projet (ex. mon-blog).
     */
    #[ApiError(409, 'projet_non_deployable', "Le projet n'est ni en ligne ni en échec (non payé, arrêté, en création ou en suppression).")]
    #[ApiError(409, 'periode_expiree', 'La période payée du projet est terminée.')]
    #[ApiError(409, 'deploiement_en_cours', 'Un déploiement du projet n\'est pas terminé ; « details » donne son identifiant.')]
    public function store(StoreDeploymentRequest $request, #[CurrentUser] User $user, string $projet, RequestDeployment $requestDeployment): JsonResponse
    {
        $deployment = $requestDeployment->handle(
            project: $this->findOwnedProject($user, $projet),
            requester: $user,
            // L'auteur (humain ou IA) vient du type de jeton, jamais d'une donnée envoyée par le client.
            actor: $user->currentAccessToken()->kind->actor(),
            branch: $request->filled('branche') ? $request->string('branche')->toString() : null,
            commitSha: $request->filled('commit') ? $request->string('commit')->toString() : null,
        );

        return (new DeploymentResource($deployment))->response()->setStatusCode(202);
    }
}
