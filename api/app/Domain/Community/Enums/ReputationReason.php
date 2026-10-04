<?php

declare(strict_types=1);

namespace App\Domain\Community\Enums;

/**
 * Raisons de gagner des points de réputation, avec le barème du README.
 */
enum ReputationReason: string
{
    /** L'auteur d'une question a accepté la réponse de ce développeur. */
    case AnswerAccepted = 'reponse_acceptee';

    /** Un autre développeur a voté « Utile » pour sa réponse (une étoile reçue). */
    case AnswerVotedUseful = 'vote_utile';

    /** Un autre développeur a donné une étoile à son projet de la vitrine. */
    case ShowcaseStarred = 'etoile_projet';

    public function points(): int
    {
        return match ($this) {
            self::AnswerAccepted => 15,
            self::AnswerVotedUseful, self::ShowcaseStarred => 5,
        };
    }
}
