<?php

declare(strict_types=1);

namespace App\Application\Showcase\Actions;

use App\Domain\Showcase\Exceptions\ImportAlreadyRunning;
use App\Domain\Showcase\Exceptions\ShowcaseLimitReached;
use App\Models\Showcase;
use App\Models\ShowcaseFile;
use Illuminate\Support\Facades\DB;

/**
 * Modifie les fichiers de la copie SamaCloud d'un projet. GitHub n'est jamais modifié.
 */
final class EditShowcaseFiles
{
    /**
     * Crée le fichier s'il n'existe pas, le remplace sinon.
     *
     * @param  string  $path  Chemin déjà validé (sûr, et accepté par le filtre de la vitrine).
     *
     * @throws ImportAlreadyRunning si un import est en cours (il remplacerait la modification).
     * @throws ShowcaseLimitReached si le projet dépasserait le nombre de fichiers ou la taille autorisés.
     */
    public function save(Showcase $showcase, string $path, string $content): ShowcaseFile
    {
        return DB::transaction(function () use ($showcase, $path, $content): ShowcaseFile {
            // Verrou : deux enregistrements simultanés ne peuvent pas dépasser les limites ensemble.
            $showcase = $this->lock($showcase);

            $existing = $showcase->files()->where('path', $path)->first();

            $maxFiles = config()->integer('samacloud.showcase.max_files');
            $maxTotalBytes = config()->integer('samacloud.showcase.max_total_bytes');

            $filesAfter = $showcase->files_count + ($existing === null ? 1 : 0);
            $bytesAfter = $showcase->total_bytes - ($existing === null ? 0 : $existing->size) + strlen($content);

            if ($filesAfter > $maxFiles || $bytesAfter > $maxTotalBytes) {
                throw new ShowcaseLimitReached($maxFiles, $maxTotalBytes);
            }

            $file = $showcase->files()->updateOrCreate(
                ['path' => $path],
                ['content' => $content, 'size' => strlen($content)],
            );

            $this->refreshTotals($showcase);

            return $file;
        });
    }

    /**
     * Supprime un fichier de la copie SamaCloud.
     *
     * @throws ImportAlreadyRunning si un import est en cours.
     */
    public function delete(Showcase $showcase, ShowcaseFile $file): void
    {
        DB::transaction(function () use ($showcase, $file): void {
            $showcase = $this->lock($showcase);

            $file->delete();

            $this->refreshTotals($showcase);
        });
    }

    private function lock(Showcase $showcase): Showcase
    {
        $showcase = Showcase::query()->whereKey($showcase->id)->lockForUpdate()->firstOrFail();

        if ($showcase->import_status->isInProgress()) {
            throw new ImportAlreadyRunning;
        }

        return $showcase;
    }

    /**
     * Recalcule le nombre de fichiers et la taille totale à partir des fichiers réellement présents.
     */
    private function refreshTotals(Showcase $showcase): void
    {
        $showcase->update([
            'files_count' => $showcase->files()->count(),
            'total_bytes' => (int) $showcase->files()->sum('size'),
        ]);
    }
}
