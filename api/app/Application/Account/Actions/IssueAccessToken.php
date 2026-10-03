<?php

declare(strict_types=1);

namespace App\Application\Account\Actions;

use App\Application\Account\Data\IssuedToken;
use App\Domain\Account\Enums\TokenAbility;
use App\Domain\Account\Enums\TokenKind;
use App\Domain\Account\Exceptions\AgentTokenLimitReached;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée les jetons d'accès : jeton de session (connexion) et jeton d'agent IA (serveur MCP).
 */
final class IssueAccessToken
{
    /** Nombre de caractères du jeton montrés pour le reconnaître (ex. « sc_live_8f3k »). */
    private const int DISPLAY_PREFIX_LENGTH = 12;

    /**
     * Jeton de session du back-office, créé à la connexion ou à l'inscription.
     */
    public function forSession(User $user, string $deviceName): IssuedToken
    {
        return $this->issue(
            user: $user,
            kind: TokenKind::Session,
            name: $deviceName,
            prefix: 'sc_sess_',
            expiresAt: CarbonImmutable::now()->addDays(config()->integer('samacloud.auth.session_token_days')),
            monthlySpendingCapFcfa: null,
        );
    }

    /**
     * Jeton d'agent IA, que le développeur colle dans la configuration de son éditeur.
     *
     * @throws AgentTokenLimitReached si le compte a déjà trop de jetons IA actifs.
     */
    public function forAgent(User $user, string $name, int $expiresInDays, ?int $monthlySpendingCapFcfa): IssuedToken
    {
        // Le compte est verrouillé le temps de compter puis de créer : deux demandes
        // simultanées ne peuvent pas dépasser la limite ensemble.
        return DB::transaction(function () use ($user, $name, $expiresInDays, $monthlySpendingCapFcfa): IssuedToken {
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            $limit = config()->integer('samacloud.auth.max_agent_tokens');

            if ($this->activeAgentTokenCount($user) >= $limit) {
                throw new AgentTokenLimitReached($limit);
            }

            return $this->issue(
                user: $user,
                kind: TokenKind::Agent,
                name: $name,
                // Le compte de démonstration reçoit des jetons de test, repérables à leur préfixe.
                prefix: $user->is_demo ? 'sc_test_' : 'sc_live_',
                expiresAt: CarbonImmutable::now()->addDays($expiresInDays),
                monthlySpendingCapFcfa: $monthlySpendingCapFcfa,
            );
        });
    }

    /**
     * Jetons IA encore utilisables. Un jeton expiré ne compte plus dans la limite.
     */
    private function activeAgentTokenCount(User $user): int
    {
        return $user->tokens()
            ->where('kind', TokenKind::Agent)
            ->where('expires_at', '>', CarbonImmutable::now())
            ->count();
    }

    private function issue(
        User $user,
        TokenKind $kind,
        string $name,
        string $prefix,
        CarbonImmutable $expiresAt,
        ?int $monthlySpendingCapFcfa,
    ): IssuedToken {
        // 48 caractères tirés d'un générateur aléatoire sûr : impossible à deviner.
        $plainTextToken = $prefix.Str::random(48);

        /** @var PersonalAccessToken $token */
        $token = $user->tokens()->create([
            'name' => $name,
            // Seule l'empreinte est enregistrée ; Sanctum retrouve le jeton en recalculant cette empreinte.
            'token' => hash('sha256', $plainTextToken),
            'abilities' => array_map(
                static fn (TokenAbility $ability): string => $ability->value,
                TokenAbility::forKind($kind),
            ),
            'expires_at' => $expiresAt,
            'kind' => $kind,
            'display_prefix' => Str::substr($plainTextToken, 0, self::DISPLAY_PREFIX_LENGTH),
            'monthly_spending_cap_fcfa' => $monthlySpendingCapFcfa,
        ]);

        return new IssuedToken($token, $plainTextToken);
    }
}
