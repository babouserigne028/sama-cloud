<?php

declare(strict_types=1);

namespace App\Application\Showcase\Actions;

use App\Application\Showcase\Jobs\ImportRepository;
use App\Domain\Showcase\Enums\ImportStatus;
use App\Domain\Showcase\Exceptions\ImportAlreadyRunning;
use App\Domain\Showcase\GitHubRepository;
use App\Models\Showcase;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Présente un projet dans la vitrine à partir de son dépôt GitHub, et lance la copie des fichiers.
 */
final class PublishShowcase
{
    /**
     * @param  list<string>  $technologySlugs  Technologies du projet (déjà validées).
     */
    public function handle(
        User $owner,
        string $title,
        string $description,
        GitHubRepository $repository,
        ?string $branch,
        ?string $demoUrl,
        array $technologySlugs,
    ): Showcase {
        return DB::transaction(function () use ($owner, $title, $description, $repository, $branch, $demoUrl, $technologySlugs): Showcase {
            $showcase = Showcase::query()->create([
                'user_id' => $owner->id,
                'title' => $title,
                'description' => $description,
                'repository_owner' => $repository->owner,
                'repository_name' => $repository->name,
                'branch' => $branch,
                'demo_url' => $demoUrl,
                'import_status' => ImportStatus::Pending,
            ]);

            $showcase->technologies()->sync(Technology::query()->whereIn('slug', $technologySlugs)->pluck('id'));

            // La tâche n'est mise en file qu'une fois le projet réellement enregistré.
            ImportRepository::dispatch($showcase->id)->afterCommit();

            return $showcase;
        });
    }

    /**
     * Relance la copie depuis GitHub : les fichiers actuels seront remplacés.
     *
     * @throws ImportAlreadyRunning si un import n'est pas terminé.
     */
    public function reimport(Showcase $showcase): Showcase
    {
        return DB::transaction(function () use ($showcase): Showcase {
            // Verrou : deux demandes simultanées ne lancent pas deux imports.
            $showcase = Showcase::query()->whereKey($showcase->id)->lockForUpdate()->firstOrFail();

            if ($showcase->import_status->isInProgress()) {
                throw new ImportAlreadyRunning;
            }

            $showcase->update(['import_status' => ImportStatus::Pending, 'import_error' => null]);

            ImportRepository::dispatch($showcase->id)->afterCommit();

            return $showcase;
        });
    }
}
