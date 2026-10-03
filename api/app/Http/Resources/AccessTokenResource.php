<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Jeton d'accès tel que renvoyé par l'API. La valeur du jeton n'y figure jamais :
 * seul son début (« prefixe ») permet de le reconnaître.
 *
 * @mixin PersonalAccessToken
 */
final class AccessTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->name,
            'type' => $this->kind,
            'prefixe' => $this->display_prefix,
            'capacites' => $this->abilities,
            'plafond_mensuel_fcfa' => $this->monthly_spending_cap_fcfa,
            'derniere_utilisation' => $this->last_used_at?->toIso8601String(),
            'expire_le' => $this->expires_at?->toIso8601String(),
            'cree_le' => $this->created_at->toIso8601String(),
        ];
    }
}
