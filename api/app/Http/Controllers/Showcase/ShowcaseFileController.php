<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Infrastructure\Showcase\ShowcaseArchiveBuilder;
use App\Models\Showcase;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'data' => $files->map(fn ($file): array => [
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
        $query = $request->validate([
            // Chemin du fichier, tel que donné par la liste des fichiers.
            'chemin' => ['required', 'string', 'max:400'],
        ]);

        $file = Showcase::query()->findOrFail($id)
            ->files()
            ->where('path', $query['chemin'])
            ->firstOrFail();

        return new JsonResponse([
            'data' => [
                'chemin' => $file->path,
                'taille_octets' => $file->size,
                'contenu' => $file->content,
            ],
        ]);
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

        // Rien à télécharger tant que l'import n'a copié aucun fichier.
        abort_if($showcase->files_count === 0, 404);

        return response()
            ->download($archiveBuilder->build($showcase), $showcase->repository_name.'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
