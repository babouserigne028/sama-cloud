<?php

declare(strict_types=1);

namespace App\Infrastructure\Showcase;

use App\Models\Showcase;
use App\Models\ShowcaseFile;
use RuntimeException;
use ZipArchive;

/**
 * Fabrique l'archive .zip d'un projet de la vitrine, à partir de la copie SamaCloud de ses fichiers.
 */
final class ShowcaseArchiveBuilder
{
    /**
     * @return string Chemin du fichier .zip temporaire. C'est à l'appelant de l'effacer après l'envoi.
     */
    public function build(Showcase $showcase): string
    {
        // Nom de fichier temporaire tiré au hasard : impossible à deviner pour un autre programme.
        $archivePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'samacloud-zip-'.bin2hex(random_bytes(16)).'.zip';

        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Impossible d\'ouvrir l\'archive en écriture.');
        }

        // Tout est rangé dans un dossier au nom du dépôt, comme une archive GitHub.
        $root = $showcase->repository_name;

        // « lazy » : les fichiers sont lus par petits lots, sans tout charger en mémoire.
        $showcase->files()->orderBy('path')->lazy(100)->each(
            fn (ShowcaseFile $file) => $zip->addFromString($root.'/'.$file->path, $file->content),
        );

        $zip->close();

        return $archivePath;
    }
}
