<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Showcase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Projet de la vitrine dans une liste : un extrait au lieu de la description complète.
 * À charger à l'avance : « owner.profile » et « technologies ».
 *
 * @mixin Showcase
 */
final class ShowcaseSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->title,
            // Début de la description (200 caractères), pour l'aperçu.
            'extrait' => Str::limit($this->description, 200),
            'technologies' => TechnologyResource::collection($this->technologies),
            'auteur' => new AuthorResource($this->owner),
            'depot' => $this->repository()->url(),
            'demo_url' => $this->demo_url,
            'nb_fichiers' => $this->files_count,
            'cree_le' => $this->created_at->toIso8601String(),
        ];
    }
}
