<?php

declare(strict_types=1);

namespace App\Application\Showcase\Actions;

use App\Models\Showcase;
use App\Models\ShowcaseComment;
use App\Models\User;
use App\Notifications\ShowcaseCommented;

/**
 * Publie un commentaire sur un projet de la vitrine et prévient son propriétaire.
 */
final class CommentShowcase
{
    public function handle(Showcase $showcase, User $author, string $body): ShowcaseComment
    {
        $comment = $showcase->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);

        // On ne se prévient pas soi-même quand on commente son propre projet.
        if ($showcase->user_id !== $author->id) {
            $author->loadMissing('profile');
            $showcase->owner->notify(new ShowcaseCommented($showcase, $comment, $author));
        }

        return $comment;
    }
}
