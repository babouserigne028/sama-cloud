<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Application\Showcase\Actions\EditShowcaseFiles;
use App\Http\Controllers\Controller;
use App\Http\OpenApi\ApiError;
use App\Http\Requests\Showcase\SaveShowcaseFileRequest;
use App\Infrastructure\Showcase\ShowcaseArchiveBuilder;
use App\Models\Showcase;
use App\Models\ShowcaseFile;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Group('Communauté — vitrine de projets', weight: 8)]
final class ShowcaseFileController extends Controller
{
    /**
     * Lister les fichiers d'un projet.
     *
     * Lecture publique. Renvoie tous les chemins et leur taille, triés : l'éditeur en fait une arborescence.
     * Le contenu de chaque fichier se lit avec GET /api/vitrine/{id}/fichiers/contenu.
     *
     * @unauthenticated
     *
     * @param  string  $id  Identifiant du projet.
     */
    public function index(string $id): JsonResponse
    {
        $showcase = Showcase::query()->findOrFail($id);

        $files = $showcase->files()->orderBy('path')->get(['path', 'size']);

        return new JsonResponse([
            'data' => $files->map(fn (ShowcaseFile $file): array => [
                // Chemin dans le dépôt (ex. app/Models/User.php).
                'chemin' => $file->path,
                'taille_octets' => $file->size,
            ])->all(),
        ]);
    }

    /**
     * Lire un fichier.
     *
     * Lecture publique. Renvoie le contenu du fichier tel quel, pour l'afficher dans l'éditeur.
     * Le code n'est jamais exécuté.
     *
     * @unauthenticated
     *
     * @param  string  $id  Identifiant du projet.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        return $this->fileResponse($this->findFile($request, Showcase::query()->findOrFail($id)));
    }

    /**
     * Enregistrer un fichier.
     *
     * Réservé au propriétaire du projet. Crée le fichier s'il n'existe pas, le remplace sinon.
     * Seule la copie SamaCloud est modifiée : GitHub ne change pas. Les mêmes règles qu'à l'import
     * s'appliquent (pas de fichier .env, pas de binaire, taille limitée).
     *
     * @param  string  $id  Identifiant du projet.
     */
    #[ApiError(409, 'import_en_cours', 'Un import est en cours : il remplacerait la modification.')]
    #[ApiError(409, 'limite_vitrine_atteinte', 'Le projet dépasserait le nombre de fichiers ou la taille autorisés.')]
    public function update(SaveShowcaseFileRequest $request, string $id, EditShowcaseFiles $editFiles): JsonResponse
    {
        $showcase = Showcase::query()->findOrFail($id);

        Gate::authorize('update', $showcase);

        $file = $editFiles->save(
            showcase: $showcase,
            path: $request->string('chemin')->toString(),
            content: (string) $request->input('contenu'),
        );

        return $this->fileResponse($file);
    }

    /**
     * Supprimer un fichier.
     *
     * Réservé au propriétaire du projet. Seule la copie SamaCloud est modifiée.
     *
     * @param  string  $id  Identifiant du projet.
     */
    #[ApiError(409, 'import_en_cours', 'Un import est en cours.')]
    public function destroy(Request $request, string $id, EditShowcaseFiles $editFiles): Response
    {
        $showcase = Showcase::query()->findOrFail($id);

        Gate::authorize('update', $showcase);

        $editFiles->delete($showcase, $this->findFile($request, $showcase));

        return response()->noContent();
    }

    /**
     * Télécharger le projet en .zip.
     *
     * Lecture publique. L'archive contient la copie SamaCloud des fichiers de code.
     *
     * @unauthenticated
     *
     * @param  string  $id  Identifiant du projet.
     */
    public function archive(string $id, ShowcaseArchiveBuilder $archiveBuilder): BinaryFileResponse
    {
        $showcase = Showcase::query()->findOrFail($id);

        // Rien à télécharger tant que le projet ne contient aucun fichier.
        abort_if($showcase->files_count === 0, 404);

        return response()
            ->download($archiveBuilder->build($showcase), $showcase->repository_name.'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * Retrouve un fichier du projet par son chemin (paramètre « chemin »), ou répond 404.
     */
    private function findFile(Request $request, Showcase $showcase): ShowcaseFile
    {
        $query = $request->validate([
            // Chemin du fichier, tel que donné par la liste des fichiers.
            'chemin' => ['required', 'string', 'max:400'],
        ]);

        return $showcase->files()->where('path', $query['chemin'])->firstOrFail();
    }

    private function fileResponse(ShowcaseFile $file): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'chemin' => $file->path,
                'taille_octets' => $file->size,
                'contenu' => $file->content,
            ],
        ]);
    }
}
