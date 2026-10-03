<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notification du compte connecté.
 *
 * @mixin DatabaseNotification
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->data;

        return [
            'id' => $this->id,
            // Nature de la notification : « reponse_recue » ou « reponse_acceptee ».
            'code' => (string) ($data['code'] ?? 'inconnu'),
            // Informations utiles pour l'afficher et ouvrir le bon écran (identifiants, titre…).
            'donnees' => array_diff_key($data, ['code' => true]),
            'lue' => $this->read_at !== null,
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
