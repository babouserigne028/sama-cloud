<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Avancement d'une opération longue.
 * La relation « deployment » doit être chargée à l'avance.
 *
 * @mixin Operation
 */
final class OperationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'statut' => $this->status,
            // Étape en cours, lisible par un humain.
            'etape' => $this->step,
            // Avancement de 0 à 100.
            'progression' => $this->progress,
            // Rempli seulement si l'opération a échoué.
            'erreur' => $this->error_code !== null
                ? ['code' => $this->error_code, 'message' => $this->error_message]
                : null,
            'projet_id' => $this->project_id,
            'deploiement_id' => $this->deployment?->id,
            'demarre_le' => $this->started_at?->toIso8601String(),
            'termine_le' => $this->finished_at?->toIso8601String(),
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
