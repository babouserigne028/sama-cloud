<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Réserve une route aux administrateurs Systalink.
 *
 * Deux conditions : le compte a le rôle administrateur, ET il agit avec un jeton
 * de session (back-office). Un jeton d'agent IA n'a jamais les droits d'administration,
 * même s'il appartient à un administrateur.
 */
final class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            throw new AccessDeniedHttpException;
        }

        if ($user->currentAccessToken()->isAgent()) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}
