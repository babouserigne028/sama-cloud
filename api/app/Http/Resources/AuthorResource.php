<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Auteur d'un contenu de la communauté : juste de quoi l'afficher et ouvrir son profil.
 * La relation « profile » doit être chargée à l'avance.
 *
 * @mixin User
 */
final class AuthorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // À utiliser avec GET /api/profils/{pseudo}.
            'pseudo' => $this->profile?->username,
            'nom' => $this->name,
            'pays' => $this->country_code,
        ];
    }
}
