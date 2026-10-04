<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ShowcaseComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Commentaire sur un projet de la vitrine.
 * À charger à l'avance : « author.profile ».
 *
 * @mixin ShowcaseComment
 */
final class ShowcaseCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Texte simple, à afficher tel quel (jamais comme du HTML).
            'contenu' => $this->body,
            'auteur' => new AuthorResource($this->author),
            'cree_le' => $this->created_at->toIso8601String(),
        ];
    }
}
