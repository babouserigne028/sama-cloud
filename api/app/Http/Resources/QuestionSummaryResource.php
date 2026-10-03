<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Question dans une liste : un extrait au lieu du contenu complet.
 * À charger à l'avance : « author.profile », « technologies » et le compteur « answers ».
 *
 * @mixin Question
 */
final class QuestionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->title,
            // Début du contenu (200 caractères), pour l'aperçu.
            'extrait' => Str::limit($this->body, 200),
            'technologies' => TechnologyResource::collection($this->technologies),
            'auteur' => new AuthorResource($this->author),
            'nb_reponses' => (int) $this->answers_count,
            // Vrai si l'auteur a accepté une réponse.
            'resolue' => $this->isResolved(),
            'cree_le' => $this->created_at->toIso8601String(),
        ];
    }
}
