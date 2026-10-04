<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Application\Showcase\Actions\DeleteShowcase;
use App\Application\Showcase\Actions\PublishShowcase;
use App\Application\Showcase\Queries\SearchShowcases;
use App\Http\Controllers\Controller;
use App\Http\OpenApi\ApiError;
use App\Http\Requests\Showcase\StoreShowcaseRequest;
use App\Http\Requests\Showcase\UpdateShowcaseRequest;
use App\Http\Resources\ShowcaseResource;
use App\Http\Resources\ShowcaseSummaryResource;
use App\Models\Showcase;
use App\Models\Technology;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

#[Group('Communauté — vitrine de projets', weight: 8)]
final class ShowcaseController extends Controller
{
    /**
     * Parcourir la vitrine.
     *
     * Lecture publique : les projets dont le code est disponible, du plus récent au plus ancien.
     * Chaque projet est résumé : « extrait » donne le début de sa description.
     *
     * @unauthenticated
     */
    public function index(Request $request, SearchShowcases $searchShowcases): AnonymousResourceCollection
    {
        $filters = $request->validate([
            // Texte cherché dans le titre et la description.
            'q' => ['nullable', 'string', 'max:100'],
            // Slug d'une technologie (ex. laravel).
            'technologie' => ['nullable', 'string', 'max:40'],
            // « recents » (par défaut) ou « etoiles » pour les projets les plus étoilés d'abord.
            'tri' => ['nullable', 'string', 'in:recents,etoiles'],
            // Nombre de projets par page (20 par défaut, 50 au plus).
            'par_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $showcases = $searchShowcases->handle(
            search: isset($filters['q']) ? trim((string) $filters['q']) : null,
            technologySlug: $filters['technologie'] ?? null,
            mostStarredFirst: ($filters['tri'] ?? 'recents') === 'etoiles',
            perPage: (int) ($filters['par_page'] ?? 20),
        );

        return ShowcaseSummaryResource::collection($showcases);
    }

    /**
     * Voir un projet de la vitrine.
     *
     * Lecture publique. « import.statut » indique si le code est disponible. Avec un jeton,
     * « etoile_par_moi » indique si le compte a déjà donné une étoile.
     *
     * @unauthenticated
     *
     * @param  string  $id  Identifiant du projet.
     */
    public function show(Request $request, string $id): ShowcaseResource
    {
        $viewer = $request->user('sanctum');

        return new ShowcaseResource($this->find($id, $viewer instanceof User ? $viewer : null));
    }

    /**
     * Présenter un projet.
     *
     * Le développeur colle l'adresse de son dépôt GitHub public. La réponse est immédiate (202) :
     * la copie des fichiers se fait en arrière-plan. Suivre « import.statut » avec GET /api/vitrine/{id}
     * jusqu'à « termine » ou « echec ». GitHub n'est jamais modifié et aucun code n'est exécuté.
     */
    public function store(StoreShowcaseRequest $request, #[CurrentUser] User $user, PublishShowcase $publishShowcase): JsonResponse
    {
        $showcase = $publishShowcase->handle(
            owner: $user,
            title: $request->string('titre')->trim()->toString(),
            description: $request->string('description')->toString(),
            repository: $request->repository(),
            branch: $request->filled('branche') ? $request->string('branche')->toString() : null,
            demoUrl: $request->filled('demo_url') ? $request->string('demo_url')->toString() : null,
            technologySlugs: array_values($request->array('technologies')),
        );

        return (new ShowcaseResource($this->find($showcase->id)))->response()->setStatusCode(202);
    }

    /**
     * Modifier la présentation de son projet.
     *
     * Réservé au propriétaire. Seuls les champs envoyés sont modifiés ; le dépôt ne se change pas.
     *
     * @param  string  $id  Identifiant du projet.
     */
    public function update(UpdateShowcaseRequest $request, string $id): ShowcaseResource
    {
        $showcase = Showcase::query()->findOrFail($id);

        Gate::authorize('update', $showcase);

        DB::transaction(function () use ($request, $showcase): void {
            $showcase->update($request->attributesToUpdate());

            if ($request->has('technologies')) {
                $showcase->technologies()->sync(
                    Technology::query()->whereIn('slug', $request->array('technologies'))->pluck('id'),
                );
            }
        });

        return new ShowcaseResource($this->find($showcase->id));
    }

    /**
     * Retirer son projet de la vitrine.
     *
     * Réservé au propriétaire, ou à un administrateur pour la modération.
     *
     * @param  string  $id  Identifiant du projet.
     */
    public function destroy(string $id, DeleteShowcase $deleteShowcase): Response
    {
        $showcase = Showcase::query()->findOrFail($id);

        Gate::authorize('delete', $showcase);

        $deleteShowcase->handle($showcase);

        return response()->noContent();
    }

    /**
     * Relancer l'import depuis GitHub.
     *
     * Réservé au propriétaire. Les fichiers de la copie SamaCloud seront remplacés par ceux du dépôt.
     * Réponse immédiate (202), comme à la création.
     *
     * @param  string  $id  Identifiant du projet.
     */
    #[ApiError(409, 'import_en_cours', 'Un import de ce dépôt n\'est pas terminé.')]
    public function reimport(string $id, PublishShowcase $publishShowcase): JsonResponse
    {
        $showcase = Showcase::query()->findOrFail($id);

        Gate::authorize('update', $showcase);

        $publishShowcase->reimport($showcase);

        return (new ShowcaseResource($this->find($showcase->id)))->response()->setStatusCode(202);
    }

    /**
     * Charge un projet avec ce dont la ressource a besoin.
     *
     * @param  User|null  $viewer  Compte connecté, s'il y en a un : sert à dire s'il a donné une étoile.
     */
    private function find(string $id, ?User $viewer = null): Showcase
    {
        $query = Showcase::query()
            ->with(['owner.profile', 'technologies'])
            ->withCount(['stargazers', 'comments']);

        if ($viewer !== null) {
            $query->withExists([
                'stargazers as starred_by_viewer' => fn (Builder $user) => $user->where('users.id', $viewer->id),
            ]);
        }

        return $query->findOrFail($id);
    }
}
