<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Community\Data\LeaderboardEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne du classement : un rang, des points et le développeur.
 *
 * @mixin LeaderboardEntry
 */
final class LeaderboardEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Rang parmi les développeurs du classement demandé ; les ex æquo partagent le même rang.
            'rang' => $this->rank,
            'points' => $this->points,
            'profil' => [
                // À utiliser avec GET /api/profils/{pseudo}.
                'pseudo' => $this->profile->username,
                'nom' => $this->profile->user->name,
                'pays' => $this->profile->user->country_code,
                'titre' => $this->profile->headline,
            ],
        ];
    }
}
