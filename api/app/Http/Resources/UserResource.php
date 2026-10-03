<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compte tel que renvoyé par l'API (champs en français).
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->name,
            'email' => $this->email,
            'pays' => $this->country_code,
            'role' => $this->role,
            'demonstration' => $this->is_demo,
            'credit_fcfa' => $this->credit_fcfa,
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
