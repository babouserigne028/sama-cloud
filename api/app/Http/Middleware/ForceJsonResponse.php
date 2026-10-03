<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oblige toutes les routes de l'API à répondre en JSON.
 *
 * Sans cela, un client qui oublie l'en-tête « Accept: application/json »
 * recevrait une page HTML ou une redirection en cas d'erreur.
 */
final class ForceJsonResponse
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
