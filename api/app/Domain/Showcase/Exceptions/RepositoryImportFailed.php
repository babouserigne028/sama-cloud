<?php

declare(strict_types=1);

namespace App\Domain\Showcase\Exceptions;

use RuntimeException;

/**
 * L'import d'un dépôt a échoué pour une raison connue. Le code est enregistré
 * sur le projet et montré au développeur.
 */
final class RepositoryImportFailed extends RuntimeException
{
    public const string NOT_FOUND = 'depot_introuvable';

    public const string TOO_LARGE = 'depot_trop_volumineux';

    public const string INVALID_ARCHIVE = 'archive_invalide';

    public const string EMPTY = 'depot_sans_code';

    public const string NETWORK = 'github_injoignable';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Import du dépôt impossible : {$reason}");
    }
}
