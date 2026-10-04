<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Application\Showcase\Actions\StarShowcase;
use App\Http\Controllers\Controller;
use App\Http\OpenApi\ApiError;
use App\Models\Showcase;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

#[Group('Communauté — vitrine de projets', weight: 8)]
final class ShowcaseStarController extends Controller
{
    /**
     * Donner une étoile à un projet.
     *
     * Une seule étoile par compte et par projet : la donner deux fois ne change rien.
     * Le propriétaire du projet gagne 5 points. Un jeton d'agent IA ne peut pas donner d'étoile.
     *
     * @param  string  $id  Identifiant du projet.
     */
    #[ApiError(403, 'etoile_sur_son_projet', 'On ne donne pas d\'étoile à son propre projet.')]
    public function store(#[CurrentUser] User $user, string $id, StarShowcase $starShowcase): JsonResponse
    {
        $showcase = Showcase::query()->findOrFail($id);

        $starShowcase->handle($showcase, $user);

        return $this->starState($showcase, true);
    }

    /**
     * Retirer son étoile.
     *
     * @param  string  $id  Identifiant du projet.
     */
    public function destroy(#[CurrentUser] User $user, string $id, StarShowcase $starShowcase): JsonResponse
    {
        $showcase = Showcase::query()->findOrFail($id);

        $starShowcase->remove($showcase, $user);

        return $this->starState($showcase, false);
    }

    /**
     * Nouvel état de l'étoile, pour mettre à jour l'affichage sans recharger le projet.
     */
    private function starState(Showcase $showcase, bool $starredByMe): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'projet_id' => $showcase->id,
                'nb_etoiles' => $showcase->stargazers()->count(),
                'etoile_par_moi' => $starredByMe,
            ],
        ]);
    }
}
