<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccessTokenResource;
use App\Http\Resources\UserResource;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

#[Group('Authentification', weight: 1)]
final class MeController extends Controller
{
    /**
     * Compte et jeton courants.
     *
     * Utilisé par le serveur MCP au démarrage pour vérifier son jeton :
     * quel compte agit, avec quel type de jeton et quelles capacités.
     */
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        return new JsonResponse([
            'data' => [
                'compte' => new UserResource($user),
                'jeton' => new AccessTokenResource($token),
            ],
        ]);
    }
}
