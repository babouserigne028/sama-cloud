<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Technology;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Technology
 */
final class TechnologyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Identifiant à utiliser dans les filtres et les formulaires (ex. « laravel »).
            'slug' => $this->slug,
            'nom' => $this->name,
        ];
    }
}
