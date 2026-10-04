<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Showcase;
use App\Models\ShowcaseComment;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Prévient le propriétaire d'un projet qu'il vient de recevoir un commentaire.
 */
final class ShowcaseCommented extends Notification
{
    public const string CODE = 'commentaire_recu';

    public function __construct(
        private readonly Showcase $showcase,
        private readonly ShowcaseComment $comment,
        private readonly User $commentAuthor,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'code' => self::CODE,
            'projet_id' => $this->showcase->id,
            'projet_titre' => $this->showcase->title,
            'commentaire_id' => $this->comment->id,
            'auteur' => [
                'pseudo' => $this->commentAuthor->profile?->username,
                'nom' => $this->commentAuthor->name,
            ],
        ];
    }
}
