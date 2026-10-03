<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Signalement. Le champ « signale_par » n'apparaît que si la relation « reporter.profile »
 * a été chargée, c'est-à-dire pour les administrateurs.
 *
 * @mixin Report
 */
final class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // « question » ou « reponse ».
            'type' => $this->content_type,
            'contenu_id' => $this->content_id,
            'motif' => $this->reason,
            'details' => $this->details,
            'statut' => $this->status,
            'signale_par' => $this->whenLoaded('reporter', fn () => new AuthorResource($this->reporter)),
            'cree_le' => $this->created_at->toIso8601String(),
            'traite_le' => $this->handled_at?->toIso8601String(),
        ];
    }
}
