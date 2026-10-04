<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Showcase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projet de la vitrine, avec sa description complète et l'état de son import.
 * À charger à l'avance : « owner.profile » et « technologies ».
 *
 * @mixin Showcase
 */
final class ShowcaseResource extends JsonResource
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
            'description' => $this->description,
            'technologies' => TechnologyResource::collection($this->technologies),
            'auteur' => new AuthorResource($this->owner),
            // Dépôt GitHub d'origine (« Voir sur GitHub »).
            'depot' => $this->repository()->url(),
            'branche' => $this->branch,
            // Version en ligne (« Voir la démo »), si le développeur l'a renseignée.
            'demo_url' => $this->demo_url,
            'import' => [
                // « en_attente », « en_cours », « termine » ou « echec ».
                'statut' => $this->import_status,
                // Code de l'erreur si l'import a échoué (ex. « depot_introuvable »).
                'erreur' => $this->import_error,
                'termine_le' => $this->imported_at?->toIso8601String(),
            ],
            'nb_fichiers' => $this->files_count,
            'taille_octets' => $this->total_bytes,
            // Vrai si le dépôt dépassait les limites : une partie des fichiers n'a pas été copiée.
            'incomplet' => $this->is_truncated,
            'cree_le' => $this->created_at->toIso8601String(),
            'modifie_le' => $this->updated_at->toIso8601String(),
        ];
    }
}
