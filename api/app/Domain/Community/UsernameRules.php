<?php

declare(strict_types=1);

namespace App\Domain\Community;

/**
 * Règles d'un pseudo de profil. Un seul endroit les définit : la validation,
 * la génération automatique et la base de données s'y réfèrent.
 */
final class UsernameRules
{
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 24;

    /** Minuscules, chiffres et tirets ; ne commence ni ne finit par un tiret. */
    public const string PATTERN = '/^[a-z0-9][a-z0-9-]{1,22}[a-z0-9]$/';

    /**
     * Pseudos interdits : ils prêteraient à confusion avec la plateforme ou ses adresses.
     *
     * @var list<string>
     */
    public const array RESERVED = [
        'admin', 'administrateur', 'api', 'moi', 'www', 'mcp', 'pay', 'support',
        'samacloud', 'systalink', 'datacloud', 'profil', 'profils', 'recherche',
    ];

    public static function isValid(string $username): bool
    {
        return preg_match(self::PATTERN, $username) === 1 && ! self::isReserved($username);
    }

    public static function isReserved(string $username): bool
    {
        return in_array($username, self::RESERVED, true);
    }
}
