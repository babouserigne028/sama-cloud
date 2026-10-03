<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\OperationResource;
use App\Models\Operation;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;

#[Group('Déploiements', weight: 4)]
final class OperationController extends Controller
{
    /**
     * Suivre une opération longue.
     *
     * Avancement d'un déploiement, d'une création ou d'une suppression : statut, étape en cours,
     * pourcentage et erreur éventuelle. À rappeler jusqu'à ce que le statut soit « reussie » ou « echouee ».
     *
     * @param  string  $id  Identifiant de l'opération, reçu dans « operation_id ».
     */
    public function show(#[CurrentUser] User $user, string $id): OperationResource
    {
        // L'opération d'un autre compte est « introuvable ».
        $operation = Operation::query()
            ->where('user_id', $user->id)
            ->with('deployment')
            ->findOrFail($id);

        return new OperationResource($operation);
    }
}
