<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Communauté — notifications', weight: 7)]
final class NotificationController extends Controller
{
    /**
     * Lister ses notifications.
     *
     * De la plus récente à la plus ancienne, 20 par page. « meta.non_lues » donne le nombre
     * de notifications pas encore lues (pour la pastille du menu).
     */
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        $notifications = $user->notifications()->paginate(20);

        return NotificationResource::collection($notifications)->additional([
            'meta' => ['non_lues' => $user->unreadNotifications()->count()],
        ]);
    }

    /**
     * Marquer une notification comme lue.
     *
     * @param  string  $id  Identifiant de la notification.
     */
    public function markAsRead(#[CurrentUser] User $user, string $id): Response
    {
        // La notification d'un autre compte est « introuvable ».
        $user->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->noContent();
    }

    /**
     * Marquer toutes ses notifications comme lues.
     */
    public function markAllAsRead(#[CurrentUser] User $user): Response
    {
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
