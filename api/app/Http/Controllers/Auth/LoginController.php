<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Account\Actions\AuthenticateUser;
use App\Application\Account\Actions\IssueAccessToken;
use App\Http\Controllers\Controller;
use App\Http\OpenApi\ApiError;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\IssuedTokenResource;
use App\Http\Resources\UserResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Authentification', weight: 1)]
final class LoginController extends Controller
{
    /**
     * Connexion.
     *
     * Vérifie l'adresse e-mail et le mot de passe, puis renvoie le compte
     * et un nouveau jeton de session pour le back-office.
     *
     * @unauthenticated
     */
    #[ApiError(401, 'identifiants_invalides', 'Adresse e-mail ou mot de passe incorrect.')]
    #[ApiError(403, 'compte_suspendu', 'Le compte a été suspendu par un administrateur.')]
    public function __invoke(LoginRequest $request, AuthenticateUser $authenticateUser, IssueAccessToken $issueAccessToken): JsonResponse
    {
        $user = $authenticateUser->handle(
            email: $request->string('email')->toString(),
            password: $request->string('mot_de_passe')->toString(),
        );

        $deviceName = $request->filled('appareil') ? $request->string('appareil')->toString() : 'Navigateur';

        $issuedToken = $issueAccessToken->forSession($user, $deviceName);

        return new JsonResponse([
            'data' => [
                'compte' => new UserResource($user),
                'jeton' => new IssuedTokenResource($issuedToken),
            ],
        ]);
    }
}
