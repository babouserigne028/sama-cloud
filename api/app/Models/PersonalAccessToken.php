<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Account\Enums\TokenAbility;
use App\Domain\Account\Enums\TokenKind;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Jeton d'accès à l'API (Sanctum), enrichi pour SamaCloud.
 *
 * Seule l'empreinte (SHA-256) du jeton est stockée : une fuite de la base
 * ne révèle aucun jeton utilisable.
 *
 * @property int $id
 * @property string $name
 * @property list<string> $abilities
 * @property TokenKind $kind
 * @property string $display_prefix
 * @property int|null $monthly_spending_cap_fcfa
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable $created_at
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'kind',
        'display_prefix',
        'monthly_spending_cap_fcfa',
    ];

    /**
     * S'ajoute aux conversions déjà définies par Sanctum (capacités, dates).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TokenKind::class,
            'monthly_spending_cap_fcfa' => 'integer',
        ];
    }

    public function isAgent(): bool
    {
        return $this->kind === TokenKind::Agent;
    }

    public function hasAbility(TokenAbility $ability): bool
    {
        return $this->can($ability->value);
    }
}
