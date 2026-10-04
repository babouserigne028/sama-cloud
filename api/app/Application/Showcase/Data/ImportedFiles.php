<?php

declare(strict_types=1);

namespace App\Application\Showcase\Data;

/**
 * Fichiers retenus après le tri d'un dépôt.
 */
final readonly class ImportedFiles
{
    /**
     * @param  array<string, string>  $files  Chemin dans le dépôt => contenu du fichier.
     * @param  bool  $truncated  Vrai si des fichiers ont été laissés de côté à cause des limites.
     */
    public function __construct(
        public array $files,
        public bool $truncated,
    ) {}

    public function totalBytes(): int
    {
        return array_sum(array_map(strlen(...), $this->files));
    }
}
