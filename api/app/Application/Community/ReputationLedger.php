<?php

declare(strict_types=1);

namespace App\Application\Community;

use App\Domain\Community\Enums\ReputationReason;
use App\Models\ReputationEvent;
use Illuminate\Support\Facades\DB;

/**
 * Registre des points de réputation. Tous les gains et retraits de points passent par ici.
 *
 * Aujourd'hui, seules les réponses rapportent des points (réponse acceptée, vote « Utile »).
 */
final class ReputationLedger
{
    /** Type de contenu des réponses dans le registre. */
    public const string ANSWER = 'reponse';

    /**
     * Donne des points. Les donner deux fois pour la même raison ne change rien :
     * la base refuse le doublon, sans erreur.
     *
     * @param  int  $beneficiaryId  Compte qui gagne les points.
     * @param  int|null  $sourceUserId  Compte à l'origine des points (votant, auteur de la question).
     */
    public function award(int $beneficiaryId, ReputationReason $reason, string $subjectId, ?int $sourceUserId): void
    {
        DB::table('reputation_events')->insertOrIgnore([
            'user_id' => $beneficiaryId,
            'reason' => $reason->value,
            'points' => $reason->points(),
            'subject_type' => self::ANSWER,
            'subject_id' => $subjectId,
            'source_user_id' => $sourceUserId,
            'created_at' => now(),
        ]);
    }

    /**
     * Retire les points donnés pour une raison précise sur une réponse.
     *
     * @param  int|null  $sourceUserId  Si renseigné, ne retire que les points venus de ce compte (un seul vote).
     */
    public function revoke(ReputationReason $reason, string $subjectId, ?int $sourceUserId = null): void
    {
        $query = ReputationEvent::query()
            ->where('reason', $reason)
            ->where('subject_type', self::ANSWER)
            ->where('subject_id', $subjectId);

        if ($sourceUserId !== null) {
            $query->where('source_user_id', $sourceUserId);
        }

        $query->delete();
    }

    /**
     * Retire tous les points rapportés par des réponses (quand elles sont supprimées).
     *
     * @param  list<string>  $answerIds
     */
    public function revokeAllForAnswers(array $answerIds): void
    {
        if ($answerIds === []) {
            return;
        }

        ReputationEvent::query()
            ->where('subject_type', self::ANSWER)
            ->whereIn('subject_id', $answerIds)
            ->delete();
    }

    /**
     * Total des points d'un développeur.
     */
    public function pointsOf(int $userId): int
    {
        return (int) ReputationEvent::query()->where('user_id', $userId)->sum('points');
    }
}
