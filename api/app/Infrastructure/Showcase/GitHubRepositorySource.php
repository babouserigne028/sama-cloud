<?php

declare(strict_types=1);

namespace App\Infrastructure\Showcase;

use App\Application\Showcase\Contracts\RepositorySource;
use App\Application\Showcase\Data\ImportedFiles;
use App\Domain\Showcase\Exceptions\RepositoryImportFailed;
use App\Domain\Showcase\FileFilter;
use App\Domain\Showcase\GitHubRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use ZipArchive;

/**
 * Récupère les fichiers d'un dépôt GitHub public en téléchargeant son archive .zip,
 * puis en triant son contenu.
 *
 * Aucun « git clone », aucune commande lancée, aucun code exécuté : l'archive est
 * seulement lue. Chaque limite ci-dessous protège le serveur contre un dépôt géant
 * ou une archive piégée.
 */
final class GitHubRepositorySource implements RepositorySource
{
    /** Au-delà de ce nombre d'entrées, on ne parcourt même pas l'archive. */
    private const int MAX_ARCHIVE_ENTRIES = 20_000;

    public function fetch(GitHubRepository $repository, ?string $branch): ImportedFiles
    {
        // Nom de fichier temporaire tiré au hasard : impossible à deviner pour un autre programme.
        $archivePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'samacloud-depot-'.bin2hex(random_bytes(16)).'.zip';

        try {
            $this->download($repository, $branch, $archivePath);

            return $this->extract($archivePath);
        } finally {
            // L'archive temporaire est toujours effacée, même en cas d'erreur.
            @unlink($archivePath);
        }
    }

    /**
     * Télécharge l'archive du dépôt dans un fichier temporaire.
     */
    private function download(GitHubRepository $repository, ?string $branch, string $archivePath): void
    {
        // L'adresse est construite ici à partir du compte et du nom validés : toujours chez GitHub.
        $url = sprintf('https://api.github.com/repos/%s/%s/zipball', rawurlencode($repository->owner), rawurlencode($repository->name));

        if ($branch !== null) {
            $url .= '/'.implode('/', array_map(rawurlencode(...), explode('/', $branch)));
        }

        try {
            $response = Http::withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'SamaCloud'])
                ->connectTimeout(10)
                ->timeout(config()->integer('samacloud.showcase.download_timeout_seconds'))
                ->get($url);
        } catch (ConnectionException) {
            throw new RepositoryImportFailed(RepositoryImportFailed::NETWORK);
        }

        // 404 : le dépôt n'existe pas, est privé, ou la branche est inconnue.
        if ($response->status() === 404) {
            throw new RepositoryImportFailed(RepositoryImportFailed::NOT_FOUND);
        }

        if (! $response->successful()) {
            throw new RepositoryImportFailed(RepositoryImportFailed::NETWORK);
        }

        $archive = $response->body();

        if (strlen($archive) > config()->integer('samacloud.showcase.max_archive_bytes')) {
            throw new RepositoryImportFailed(RepositoryImportFailed::TOO_LARGE);
        }

        file_put_contents($archivePath, $archive);
    }

    /**
     * Lit l'archive et ne garde que les fichiers de code acceptés, dans les limites fixées.
     */
    private function extract(string $archivePath): ImportedFiles
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new RepositoryImportFailed(RepositoryImportFailed::INVALID_ARCHIVE);
        }

        try {
            if ($zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
                throw new RepositoryImportFailed(RepositoryImportFailed::TOO_LARGE);
            }

            $maxFiles = config()->integer('samacloud.showcase.max_files');
            $maxFileBytes = config()->integer('samacloud.showcase.max_file_bytes');
            $maxTotalBytes = config()->integer('samacloud.showcase.max_total_bytes');

            $files = [];
            $totalBytes = 0;
            $truncated = false;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);

                if ($entry === false || str_ends_with($entry['name'], '/')) {
                    continue;
                }

                // GitHub range tout dans un dossier racine « depot-abc123/ » : on l'enlève.
                $path = $this->withoutRootFolder($entry['name']);

                if ($path === null || ! FileFilter::accepts($path)) {
                    continue;
                }

                // La taille annoncée est vérifiée AVANT de lire le fichier : une archive piégée
                // (« zip bomb ») ne peut pas remplir la mémoire.
                if ($entry['size'] > $maxFileBytes) {
                    $truncated = true;

                    continue;
                }

                if (count($files) >= $maxFiles || $totalBytes + $entry['size'] > $maxTotalBytes) {
                    $truncated = true;

                    break;
                }

                $content = $zip->getFromIndex($index, $maxFileBytes + 1);

                // Taille réelle différente de la taille annoncée : entrée suspecte, on l'ignore.
                if ($content === false || strlen($content) !== $entry['size'] || ! FileFilter::isText($content)) {
                    continue;
                }

                $files[$path] = $content;
                $totalBytes += strlen($content);
            }
        } finally {
            $zip->close();
        }

        if ($files === []) {
            throw new RepositoryImportFailed(RepositoryImportFailed::EMPTY);
        }

        ksort($files, SORT_STRING);

        return new ImportedFiles($files, $truncated);
    }

    /**
     * « depot-abc123/src/app.php » devient « src/app.php ». Une entrée à la racine de l'archive est ignorée.
     */
    private function withoutRootFolder(string $entryName): ?string
    {
        $separator = strpos($entryName, '/');

        if ($separator === false) {
            return null;
        }

        $path = substr($entryName, $separator + 1);

        return $path === '' ? null : $path;
    }
}
