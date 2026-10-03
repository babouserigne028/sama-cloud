<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Project\Enums\ServiceType;
use App\Models\Project;
use App\Models\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projet tel que renvoyé par l'API, avec ses services et ses bases.
 * Les relations « services » et « databases » doivent être chargées à l'avance.
 *
 * @mixin Project
 */
final class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->name,
            'statut' => $this->status,
            // Adresse principale : celle du premier service web en ligne.
            'url' => $this->primaryUrl(),
            'depot' => $this->repository_url,
            'branche' => $this->branch,
            // Fin de la période payée.
            'echeance' => $this->paid_until?->toIso8601String(),
            'services' => ProjectServiceResource::collection($this->services),
            'bases' => ProjectDatabaseResource::collection($this->databases),
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }

    private function primaryUrl(): ?string
    {
        $webService = $this->services->first(
            fn (ProjectService $service): bool => $service->type === ServiceType::Web && $service->hostname !== null,
        );

        return $webService !== null ? 'https://'.$webService->hostname : null;
    }
}
