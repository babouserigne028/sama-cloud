<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProjectDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base de données d'un projet. Les identifiants de connexion ne sont jamais renvoyés.
 *
 * @mixin ProjectDatabase
 */
final class ProjectDatabaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nom' => $this->name,
            'type' => $this->engine,
            'version' => $this->version,
            'taille' => $this->size,
        ];
    }
}
