<?php

declare(strict_types=1);

namespace App\Http\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Documente, pour chaque route, les erreurs qu'elle peut renvoyer avec leur « code ».
 *
 * L'outil de documentation devine déjà les erreurs possibles (401, 404, 422), mais
 * avec le format par défaut de Laravel. On les remplace par les nôtres, et on ajoute
 * celles qu'il ne peut pas deviner (compte suspendu, capacité manquante, limite de débit…).
 */
final class ErrorResponsesTransformer implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $successResponses = [];
        $detectedStatuses = [];

        // On garde les réponses de succès et on note quelles erreurs l'outil a détectées.
        foreach ($operation->responses ?? [] as $response) {
            $status = (int) ($response instanceof Reference ? $response->resolve()->code : $response->code);

            if ($status >= 400) {
                $detectedStatuses[] = $status;
            } else {
                $successResponses[] = $response;
            }
        }

        $errors = $this->errorsFor($routeInfo, $detectedStatuses);
        ksort($errors);

        $errorResponses = [];

        foreach ($errors as $status => $lines) {
            $errorResponses[] = (new Response($status))->setDescription(implode("\n", $lines));
        }

        $operation->responses = [...$successResponses, ...$errorResponses];
    }

    /**
     * @param  list<int>  $detectedStatuses
     * @return array<int, list<string>> Pour chaque statut HTTP, une ligne par code d'erreur possible.
     */
    private function errorsFor(RouteInfo $routeInfo, array $detectedStatuses): array
    {
        $middleware = collect($routeInfo->route->gatherMiddleware())->filter(fn ($name) => is_string($name));
        $errors = [];

        if ($middleware->contains(fn (string $name) => Str::startsWith($name, 'auth:'))) {
            $errors[401][] = $this->line('non_authentifie', 'Jeton absent, expiré ou révoqué.');
            $errors[403][] = $this->line('compte_suspendu', 'Le compte a été suspendu par un administrateur.');
        }

        if ($middleware->contains(fn (string $name) => Str::startsWith($name, ['abilities:', 'ability:']))) {
            $errors[403][] = $this->line('capacite_manquante', "Le jeton n'a pas la capacité exigée par cette route.");
        }

        if (in_array(404, $detectedStatuses, true) || $routeInfo->route->parameterNames() !== []) {
            $errors[404][] = $this->line('introuvable', "La ressource n'existe pas ou n'appartient pas à ce compte.");
        }

        if (in_array(422, $detectedStatuses, true)) {
            $errors[422][] = $this->line('validation_echouee', 'Données invalides ; « details » liste chaque champ fautif.');
        }

        $errors[429][] = $this->line('trop_de_requetes', 'Limite de débit atteinte ; voir « details.reessayer_dans_secondes ».');

        // Erreurs métier déclarées sur l'action avec #[ApiError(...)].
        foreach ($routeInfo->reflectionMethod()?->getAttributes(ApiError::class) ?? [] as $attribute) {
            $error = $attribute->newInstance();
            $errors[$error->status][] = $this->line($error->code, $error->description);
        }

        return $errors;
    }

    private function line(string $code, string $description): string
    {
        return "- `{$code}` — {$description}";
    }
}
