<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Classe mère de toutes les erreurs métier de SamaCloud.
 *
 * Une erreur métier est une erreur « prévue » : quota atteint, lien expiré,
 * nom de confirmation incorrect… Elle porte un code stable (lu par le
 * serveur MCP et le back-office) et un message en français (lu par l'humain
 * ou expliqué par l'IA).
 *
 * Cette classe n'importe rien de Laravel : le domaine reste indépendant
 * du framework.
 */
abstract class BusinessException extends RuntimeException
{
    /**
     * @param  string  $message  Message en français, affichable tel quel.
     * @param  array<int|string, mixed>  $details  Précisions facultatives (ex. champ fautif, limite atteinte).
     */
    public function __construct(
        string $message,
        private readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Code stable de l'erreur, en minuscules avec des tirets bas (ex. « quota_atteint »).
     * Il ne doit jamais changer une fois publié : les clients s'appuient dessus.
     */
    abstract public function errorCode(): string;

    /**
     * Famille de l'erreur, utilisée pour choisir le statut HTTP.
     */
    abstract public function category(): ErrorCategory;

    /**
     * @return array<int|string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
