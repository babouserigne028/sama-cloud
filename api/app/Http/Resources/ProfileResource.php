<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Profil public d'un développeur. Il ne contient jamais l'adresse e-mail.
 * Les relations « user » et « technologies » doivent être chargées à l'avance,
 * ainsi que le total « points » (withSum).
 *
 * @mixin Profile
 */
final class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pseudo' => $this->username,
            'nom' => $this->user->name,
            // Phrase de présentation courte.
            'titre' => $this->headline,
            'bio' => $this->bio,
            // Code pays sur 2 lettres.
            'pays' => $this->user->country_code,
            'disponibilite' => $this->availability,
            // Points de réputation : 15 par réponse acceptée, 5 par vote « Utile » reçu.
            'points' => (int) ($this->resource->getAttributes()['points'] ?? 0),
            'technologies' => TechnologyResource::collection($this->technologies),
            'liens' => [
                'github' => $this->github_url,
                'site' => $this->website_url,
                'linkedin' => $this->linkedin_url,
            ],
            'membre_depuis' => $this->user->created_at?->toIso8601String(),
        ];
    }
}
