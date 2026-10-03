<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Account\Actions\IssueAccessToken;
use App\Application\Account\Actions\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\OpenApi\ApiError;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\IssuedTokenResource;
use App\Http\Resources\UserResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Authentification', weight: 1)]
final class RegisterController extends Controller
{
    /**
     * Inscription.
     *
     * Crée un compte de développeur et le connecte aussitôt : la réponse contient
     * le compte et un jeton de session.
     *
     * @unauthenticated
     */
    #[ApiError(409, 'email_deja_utilise', 'Deux inscriptions simultanées avec la même adresse.')]
    public function __invoke(RegisterRequest $request, RegisterUser $registerUser, IssueAccessToken $issueAccessToken): JsonResponse
    {
        $user = $registerUser->handle(
            name: $request->string('nom')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('mot_de_passe')->toString(),
            countryCode: $request->filled('pays') ? $request->string('pays')->toString() : null,
        );

        $issuedToken = $issueAccessToken->forSession($user, 'Inscription');

        return new JsonResponse([
            'data' => [
                'compte' => new UserResource($user),
                'jeton' => new IssuedTokenResource($issuedToken),
            ],
        ], 201);
    }
}
