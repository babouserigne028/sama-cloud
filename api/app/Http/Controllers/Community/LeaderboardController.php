<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Application\Community\Queries\Leaderboard;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeaderboardEntryResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

#[Group('Communauté — profils', weight: 5)]
final class LeaderboardController extends Controller
{
    /**
     * Classement des développeurs.
     *
     * Lecture publique. Les meilleurs développeurs par points de réputation, pour toute la communauté
     * ou pour un pays, depuis toujours ou pour le mois en cours. Le rang est calculé dans le classement
     * demandé : avec « pays=SN », c'est le rang au Sénégal.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, Leaderboard $leaderboard): AnonymousResourceCollection
    {
        $request->merge(['pays' => is_string($request->query('pays')) ? Str::upper(trim($request->query('pays'))) : null]);

        $filters = $request->validate([
            // Code pays sur 2 lettres (ex. SN). Absent = toute la communauté.
            'pays' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            // « tout » (par défaut) ou « mois » pour les points gagnés depuis le début du mois.
            'periode' => ['nullable', 'string', 'in:tout,mois'],
            // Nombre de développeurs par page (20 par défaut, 50 au plus).
            'par_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $entries = $leaderboard->handle(
            countryCode: $filters['pays'] ?? null,
            thisMonthOnly: ($filters['periode'] ?? 'tout') === 'mois',
            perPage: (int) ($filters['par_page'] ?? 20),
        );

        return LeaderboardEntryResource::collection($entries);
    }
}
