<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Account\Exceptions\AccountSuspended;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque un compte suspendu, même s'il possède encore un jeton valide.
 * La suspension prend donc effet immédiatement, sans attendre l'expiration des jetons.
 */
final class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isSuspended()) {
            throw new AccountSuspended;
        }

        return $next($request);
    }
}
