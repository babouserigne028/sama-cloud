<?php

declare(strict_types=1);

namespace App\Http\Errors;

use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Exceptions\ErrorCategory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Transforme toute erreur de l'API en une réponse JSON au format commun :
 *
 *     { "code": "...", "message": "...", "details": ... }
 *
 * - « code » : identifiant stable, lu par les programmes (serveur MCP, back-office).
 * - « message » : phrase en français, lue par l'humain ou expliquée par l'IA.
 * - « details » : présent seulement s'il y a des précisions à donner.
 *
 * Un seul endroit décide du format : aucune erreur ne sort de l'API sous une autre forme.
 */
final class ApiErrorRenderer
{
    /**
     * Code et message de chaque statut HTTP courant.
     *
     * Le message est fixe : on n'affiche jamais le message technique de
     * l'exception, qui peut révéler des noms de classes ou de tables.
     *
     * @var array<int, array{code: string, message: string}>
     */
    private const array HTTP_ERRORS = [
        400 => ['code' => 'requete_invalide', 'message' => 'La requête est mal formée.'],
        403 => ['code' => 'acces_refuse', 'message' => "Vous n'avez pas le droit d'effectuer cette action."],
        404 => ['code' => 'introuvable', 'message' => 'La ressource demandée est introuvable.'],
        405 => ['code' => 'methode_non_autorisee', 'message' => "Cette méthode HTTP n'est pas autorisée sur cette adresse."],
        413 => ['code' => 'contenu_trop_volumineux', 'message' => 'Le contenu envoyé est trop volumineux.'],
        419 => ['code' => 'session_expiree', 'message' => 'La session a expiré. Reconnectez-vous.'],
        429 => ['code' => 'trop_de_requetes', 'message' => 'Trop de requêtes. Patientez avant de réessayer.'],
        503 => ['code' => 'service_indisponible', 'message' => 'Le service est momentanément indisponible.'],
    ];

    /**
     * Renvoie la réponse JSON de l'erreur, ou null pour laisser Laravel
     * faire son rendu habituel (pages hors API).
     */
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $this->concerns($request)) {
            return null;
        }

        // Cette exception transporte déjà une réponse complète : on n'y touche pas.
        if ($exception instanceof HttpResponseException) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => $this->renderValidation($exception),
            $exception instanceof AuthenticationException => $this->respond(
                401,
                'non_authentifie',
                'Authentification requise : jeton absent, expiré ou révoqué.',
            ),
            $exception instanceof BusinessException => $this->respond(
                $this->statusFor($exception->category()),
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->details(),
            ),
            $exception instanceof HttpExceptionInterface => $this->renderHttp($exception),
            default => $this->renderUnexpected($exception),
        };
    }

    /**
     * Seules les routes de l'API et les clients qui demandent du JSON sont concernés.
     */
    private function concerns(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * Erreurs de validation : une ligne par champ fautif, pour que
     * le client (ou l'IA) sache exactement quoi corriger.
     */
    private function renderValidation(ValidationException $exception): JsonResponse
    {
        $details = [];

        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $details[] = ['champ' => $field, 'message' => $message];
            }
        }

        $count = count($details);

        $message = $count > 1
            ? "Les données envoyées contiennent {$count} erreurs."
            : 'Les données envoyées contiennent 1 erreur.';

        return $this->respond($exception->status, 'validation_echouee', $message, $details);
    }

    /**
     * Erreurs HTTP classiques (404, 403, 405, 429…).
     */
    private function renderHttp(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();
        $headers = $exception->getHeaders();

        // Jeton valide, mais sans la capacité demandée par la route (ex. un jeton IA
        // qui tente de gérer des jetons). On le dit clairement pour que l'IA comprenne le refus.
        $cause = $exception->getPrevious();

        if ($cause instanceof MissingAbilityException) {
            return $this->respond(
                403,
                'capacite_manquante',
                "Ce jeton n'a pas la capacité nécessaire pour cette action.",
                ['capacites_requises' => array_values($cause->abilities())],
            );
        }

        $error = self::HTTP_ERRORS[$status] ?? [
            'code' => 'erreur_http',
            'message' => 'La requête a échoué.',
        ];

        // Pour une limite de débit, on indique dans combien de secondes réessayer.
        $details = [];
        $retryAfter = $headers['Retry-After'] ?? null;

        if ($status === 429 && is_numeric($retryAfter)) {
            $details = ['reessayer_dans_secondes' => (int) $retryAfter];
        }

        return $this->respond($status, $error['code'], $error['message'], $details, $headers);
    }

    /**
     * Erreur imprévue (bogue, panne) : le client reçoit un message neutre.
     * Le détail technique reste dans les journaux du serveur, sauf en mode
     * débogage où il est renvoyé pour aider le développeur.
     */
    private function renderUnexpected(Throwable $exception): JsonResponse
    {
        $details = [];

        if (config('app.debug') === true) {
            $details = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'emplacement' => $exception->getFile().':'.$exception->getLine(),
            ];
        }

        return $this->respond(
            500,
            'erreur_interne',
            'Une erreur interne est survenue. Réessayez dans quelques instants.',
            $details,
        );
    }

    /**
     * Traduit la famille d'une erreur métier en statut HTTP.
     */
    private function statusFor(ErrorCategory $category): int
    {
        return match ($category) {
            ErrorCategory::Invalid => 422,
            ErrorCategory::Unauthenticated => 401,
            ErrorCategory::Forbidden => 403,
            ErrorCategory::NotFound => 404,
            ErrorCategory::Conflict => 409,
        };
    }

    /**
     * Construit la réponse au format commun. « details » n'apparaît que s'il est rempli.
     *
     * @param  array<int|string, mixed>  $details
     * @param  array<string, mixed>  $headers
     */
    private function respond(int $status, string $code, string $message, array $details = [], array $headers = []): JsonResponse
    {
        $body = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $body['details'] = $details;
        }

        return new JsonResponse($body, $status, $headers);
    }
}
