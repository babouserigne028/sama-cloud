<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Déploiement tel que renvoyé par l'API.
 *
 * @mixin Deployment
 */
final class DeploymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statut' => $this->status,
            // Qui a demandé ce déploiement : « humain », « ia » ou « systeme ».
            'acteur' => $this->actor,
            'branche' => $this->branch,
            'commit' => $this->commit_sha,
            // Identifiant à passer à GET /api/operations/{id} pour suivre l'avancement.
            'operation_id' => $this->operation_id,
            'raison_echec' => $this->failure_reason,
            'demarre_le' => $this->started_at?->toIso8601String(),
            'termine_le' => $this->finished_at?->toIso8601String(),
            // Durée en secondes, connue une fois le déploiement terminé.
            'duree_secondes' => $this->started_at !== null && $this->finished_at !== null
                ? (int) $this->started_at->diffInSeconds($this->finished_at)
                : null,
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
