<?php

declare(strict_types=1);

namespace App\Application\Showcase\Jobs;

use App\Application\Showcase\Contracts\RepositorySource;
use App\Domain\Showcase\Enums\ImportStatus;
use App\Domain\Showcase\Exceptions\RepositoryImportFailed;
use App\Domain\Showcase\GitHubRepository;
use App\Models\Showcase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copie les fichiers d'un dépôt GitHub dans un projet de la vitrine.
 *
 * C'est une opération longue : elle tourne en arrière-plan, dans la file d'attente.
 * Le projet passe par « en_cours », puis « termine » ou « echec ».
 */
final class ImportRepository implements ShouldQueue
{
    use Queueable;

    /** Un seul essai : en cas d'échec, c'est le développeur qui relance l'import. */
    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly string $showcaseId) {}

    public function handle(RepositorySource $source): void
    {
        $showcase = Showcase::query()->find($this->showcaseId);

        // Le projet a pu être supprimé avant que le worker ne prenne la tâche.
        if ($showcase === null) {
            return;
        }

        $showcase->update(['import_status' => ImportStatus::Running, 'import_error' => null]);

        try {
            $imported = $source->fetch(
                GitHubRepository::fromParts($showcase->repository_owner, $showcase->repository_name),
                $showcase->branch,
            );
        } catch (RepositoryImportFailed $failure) {
            $showcase->update(['import_status' => ImportStatus::Failed, 'import_error' => $failure->reason]);

            return;
        }

        // Les anciens fichiers sont remplacés d'un seul coup : jamais de projet à moitié importé.
        DB::transaction(function () use ($showcase, $imported): void {
            $showcase->files()->delete();

            $now = now();
            $rows = [];

            foreach ($imported->files as $path => $content) {
                $rows[] = [
                    'id' => (string) Str::ulid(),
                    'showcase_id' => $showcase->id,
                    'path' => $path,
                    'content' => $content,
                    'size' => strlen($content),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('showcase_files')->insert($chunk);
            }

            $showcase->update([
                'import_status' => ImportStatus::Done,
                'import_error' => null,
                'imported_at' => $now,
                'files_count' => count($rows),
                'total_bytes' => $imported->totalBytes(),
                'is_truncated' => $imported->truncated,
            ]);
        });
    }

    /**
     * Erreur imprévue (panne, bogue) : le projet ne reste pas bloqué « en cours ».
     */
    public function failed(?Throwable $exception): void
    {
        Showcase::query()
            ->whereKey($this->showcaseId)
            ->update(['import_status' => ImportStatus::Failed, 'import_error' => 'erreur_interne']);
    }
}
