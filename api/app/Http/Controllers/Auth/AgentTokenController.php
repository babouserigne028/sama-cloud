<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Account\Actions\IssueAccessToken;
use App\Domain\Account\Enums\TokenKind;
use App\Http\Controllers\Controller;
use App\Http\OpenApi\ApiError;
use App\Http\Requests\Auth\StoreAgentTokenRequest;
use App\Http\Resources\AccessTokenResource;
use App\Http\Resources\IssuedTokenResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Gestion des jetons d'agent IA depuis le back-office (page « Accès IA »).
 * Ces routes sont interdites aux jetons IA eux-mêmes.
 */
#[Group('Jetons IA', weight: 2)]
final class AgentTokenController extends Controller
{
    /**
     * Lister ses jetons IA.
     *
     * Renvoie le nom, le début du jeton, les dates et le plafond — jamais la valeur du jeton.
     */
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        $tokens = $user->tokens()
            ->where('kind', TokenKind::Agent)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return AccessTokenResource::collection($tokens);
    }

    /**
     * Créer un jeton IA.
     *
     * La valeur du jeton est renvoyée une seule fois, dans le champ « valeur ».
     */
    #[ApiError(409, 'limite_jetons_atteinte', 'Le compte a déjà le nombre maximum de jetons IA actifs.')]
    public function store(StoreAgentTokenRequest $request, #[CurrentUser] User $user, IssueAccessToken $issueAccessToken): JsonResponse
    {
        $issuedToken = $issueAccessToken->forAgent(
            user: $user,
            name: $request->string('nom')->toString(),
            expiresInDays: $request->integer('expiration_jours', config()->integer('samacloud.auth.agent_token_default_days')),
            monthlySpendingCapFcfa: $request->filled('plafond_mensuel_fcfa') ? $request->integer('plafond_mensuel_fcfa') : null,
        );

        return (new IssuedTokenResource($issuedToken))->response()->setStatusCode(201);
    }

    /**
     * Révoquer un jeton IA.
     *
     * Effet immédiat. Un jeton d'un autre compte, ou un jeton de session, est « introuvable ».
     */
    public function destroy(#[CurrentUser] User $user, int $id): Response
    {
        $user->tokens()
            ->where('kind', TokenKind::Agent)
            ->whereKey($id)
            ->firstOrFail()
            ->delete();

        return response()->noContent();
    }
}
