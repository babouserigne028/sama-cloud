<?php

declare(strict_types=1);

namespace App\Application\Community\Queries;

use App\Application\Community\Data\LeaderboardEntry;
use App\Models\Profile;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Classement des développeurs par points de réputation.
 */
final class Leaderboard
{
    /**
     * Le rang est calculé parmi les développeurs retenus par les filtres : avec un pays,
     * c'est donc le classement de ce pays. Deux développeurs à égalité ont le même rang.
     * Les comptes suspendus et les développeurs sans point n'apparaissent pas.
     *
     * @param  string|null  $countryCode  Code pays sur 2 lettres, ou null pour toute la communauté.
     * @param  bool  $thisMonthOnly  true = seulement les points gagnés depuis le début du mois.
     * @return LengthAwarePaginator<int, LeaderboardEntry>
     */
    public function handle(?string $countryCode, bool $thisMonthOnly, int $perPage): LengthAwarePaginator
    {
        $totals = DB::table('reputation_events')
            ->join('users', 'users.id', '=', 'reputation_events.user_id')
            ->whereNull('users.suspended_at')
            ->selectRaw('reputation_events.user_id, SUM(reputation_events.points) AS points')
            ->groupBy('reputation_events.user_id')
            ->havingRaw('SUM(reputation_events.points) > 0');

        if ($countryCode !== null) {
            $totals->where('users.country_code', $countryCode);
        }

        if ($thisMonthOnly) {
            $totals->where('reputation_events.created_at', '>=', CarbonImmutable::now()->startOfMonth());
        }

        // RANK() donne le même rang aux ex æquo, puis saute les rangs occupés (1, 2, 2, 4…).
        $ranking = DB::query()
            ->fromSub($totals, 'totals')
            ->selectRaw('user_id, points, RANK() OVER (ORDER BY points DESC) AS rank')
            ->orderByDesc('points')
            ->orderBy('user_id')
            ->paginate($perPage);

        // Les profils de la page sont chargés en une seule requête.
        $profiles = Profile::query()
            ->with('user')
            ->whereIn('user_id', $ranking->getCollection()->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        /** @var LengthAwarePaginator<int, LeaderboardEntry> $entries */
        $entries = $ranking->through(fn (stdClass $row): LeaderboardEntry => new LeaderboardEntry(
            rank: (int) $row->rank,
            points: (int) $row->points,
            profile: $profiles->firstOrFail(fn (Profile $profile): bool => $profile->user_id === (int) $row->user_id),
        ));

        return $entries;
    }
}
