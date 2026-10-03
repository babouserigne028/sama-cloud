<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Account\Data\IssuedToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Jeton qui vient d'être créé : mêmes champs qu'un jeton, plus sa « valeur » en clair.
 * C'est le seul moment où la valeur est montrée.
 *
 * @mixin IssuedToken
 */
final class IssuedTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new AccessTokenResource($this->token))->toArray($request),
            'valeur' => $this->plainTextToken,
        ];
    }
}
