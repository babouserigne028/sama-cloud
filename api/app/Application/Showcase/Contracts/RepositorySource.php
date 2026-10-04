<?php

declare(strict_types=1);

namespace App\Application\Showcase\Contracts;

use App\Application\Showcase\Data\ImportedFiles;
use App\Domain\Showcase\Exceptions\RepositoryImportFailed;
use App\Domain\Showcase\GitHubRepository;

/**
 * Source des fichiers d'un dépôt. L'application ne sait pas comment ils sont obtenus
 * (téléchargement d'une archive GitHub aujourd'hui) : elle demande seulement les fichiers triés.
 *
 * Dans les tests, cette source est remplacée par une fausse, sans appel à GitHub.
 */
interface RepositorySource
{
    /**
     * @param  string|null  $branch  Branche à lire ; null = la branche par défaut du dépôt.
     *
     * @throws RepositoryImportFailed si le dépôt est introuvable, trop volumineux, illisible ou vide.
     */
    public function fetch(GitHubRepository $repository, ?string $branch): ImportedFiles;
}
