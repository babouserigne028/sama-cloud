<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Question complète, avec ses réponses.
 * À charger à l'avance : « author.profile », « technologies », « answers » et le compteur « answers ».
 *
 * @mixin Question
 */
final class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->title,
            // Markdown brut : c'est à l'affichage de le mettre en forme et de le nettoyer.
            'contenu' => $this->body,
            'technologies' => TechnologyResource::collection($this->technologies),
            'auteur' => new AuthorResource($this->author),
            'nb_reponses' => (int) $this->answers_count,
            'resolue' => $this->isResolved(),
            'reponse_acceptee_id' => $this->accepted_answer_id,
            // La réponse acceptée en premier, puis les plus utiles, puis les plus anciennes.
            'reponses' => AnswerResource::collection($this->answers),
            'cree_le' => $this->created_at->toIso8601String(),
            'modifie_le' => $this->updated_at->toIso8601String(),
        ];
    }
}
