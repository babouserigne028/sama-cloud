<?php

declare(strict_types=1);

namespace App\Application\Community;

use App\Domain\Community\Enums\ReputationReason;
use App\Models\ReputationEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Registre des points de réputation. Tous les gains et retraits de points passent par ici.
 *
 * Les points viennent des réponses (réponse acceptée, vote « Utile ») et des projets de la vitrine (étoile).
 */
final class ReputationLedger
{
    /** Type de contenu des réponses dans le registre. */
    public const string ANSWER = 'reponse';

    /** Type de contenu des projets de la vitrine dans le registre. */
    public const string SHOWCASE = 'vitrine';

    /**
     * Donne des points. Les donner deux fois pour la même raison ne change rien :
     * la base refuse le doublon, sans erreur. Le geste d'un compte trop récent ne rapporte rien.
     *
     * @param  int  $beneficiaryId  Compte qui gagne les points.
     * @param  int|null  $sourceUserId  Compte à l'origine des points (votant, auteur de la question).
     */
    public function award(int $beneficiaryId, ReputationReason $reason, string $subjectId, ?int $sourceUserId, string $subjectType = self::ANSWER): void
    {
        if ($sourceUserId !== null && $this->isTooRecent($sourceUserId)) {
            return;
        }

        DB::table('reputation_events')->insertOrIgnore([
            'user_id' => $beneficiaryId,
            'reason' => $reason->value,
            'points' => $reason->points(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'source_user_id' => $sourceUserId,
            'created_at' => now(),
        ]);
    }

    /**
     * Anti-triche : le geste d'un compte tout juste créé (vote, réponse acceptée) reste enregistré,
     * mais ne rapporte pas de points. Sans cela, il suffirait de créer des comptes pour monter au classement.
     */
    private function isTooRecent(int $userId): bool
    {
        $minimumHours = config()->integer('samacloud.community.min_account_age_hours_for_points');

        if ($minimumHours <= 0) {
            return false;
        }

        return User::query()
            ->whereKey($userId)
            ->where('created_at', '>', CarbonImmutable::now()->subHours($minimumHours))
            ->exists();
    }

    /**
     * Retire les points donnés pour une raison précise sur une réponse.
     *
     * @param  int|null  $sourceUserId  Si renseigné, ne retire que les points venus de ce compte (un seul vote).
     */
    public function revoke(ReputationReason $reason, string $subjectId, ?int $sourceUserId = null, string $subjectType = self::ANSWER): void
    {
        $query = ReputationEvent::query()
            ->where('reason', $reason)
            ->where('subject_type', $subjectType)
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
        $this->revokeAllFor(self::ANSWER, $answerIds);
    }

    /**
     * Retire tous les points rapportés par des contenus d'un type donné (quand ils sont supprimés).
     *
     * @param  list<string>  $subjectIds
     */
    public function revokeAllFor(string $subjectType, array $subjectIds): void
    {
        if ($subjectIds === []) {
            return;
        }

        ReputationEvent::query()
            ->where('subject_type', $subjectType)
            ->whereIn('subject_id', $subjectIds)
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
