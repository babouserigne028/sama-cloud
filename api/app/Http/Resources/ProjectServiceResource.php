<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Service d'un projet (web ou worker).
 *
 * @mixin ProjectService
 */
final class ProjectServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nom' => $this->name,
            'type' => $this->type,
            'framework' => $this->framework,
            'taille' => $this->size,
            // Adresse publique en HTTPS. Vide pour un worker ou tant que le service n'est pas en ligne.
            'url' => $this->hostname !== null ? 'https://'.$this->hostname : null,
        ];
    }
}
