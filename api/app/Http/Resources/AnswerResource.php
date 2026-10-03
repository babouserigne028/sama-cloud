<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réponse à une question.
 * À charger à l'avance : « author.profile », « question » et le compteur « voters ».
 *
 * @mixin Answer
 */
final class AnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Markdown brut : c'est à l'affichage de le mettre en forme et de le nettoyer.
            'contenu' => $this->body,
            'auteur' => new AuthorResource($this->author),
            // Nombre de votes « Utile ».
            'nb_utile' => (int) $this->voters_count,
            // Vrai si l'auteur de la question a accepté cette réponse.
            'acceptee' => $this->question->accepted_answer_id === $this->id,
            // Vrai si le compte connecté a voté « Utile ». Toujours faux sans jeton.
            'vote_par_moi' => (bool) ($this->resource->getAttributes()['voted_by_viewer'] ?? false),
            'cree_le' => $this->created_at->toIso8601String(),
            'modifie_le' => $this->updated_at->toIso8601String(),
        ];
    }
}
