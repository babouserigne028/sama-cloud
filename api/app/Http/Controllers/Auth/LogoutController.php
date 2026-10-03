<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

#[Group('Authentification', weight: 1)]
final class LogoutController extends Controller
{
    /**
     * Déconnexion.
     *
     * Révoque le jeton de session utilisé pour cet appel. Les autres sessions
     * et les jetons IA du compte restent valides.
     */
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }
}
