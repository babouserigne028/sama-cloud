<?php

declare(strict_types=1);

namespace App\Domain\Showcase;

/**
 * Dépôt GitHub public, réduit à ce qui l'identifie : un compte et un nom.
 *
 * L'adresse collée par le développeur n'est jamais appelée telle quelle. On en extrait
 * le compte et le nom, et c'est SamaCloud qui construit l'adresse de téléchargement :
 * impossible de faire appeler un autre serveur que GitHub.
 */
final readonly class GitHubRepository
{
    /** https://github.com/<compte>/<depot>, avec « .git » et « / » final facultatifs. */
    private const string URL_PATTERN = '#^https://github\.com/([A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?)/([A-Za-z0-9._-]{1,100}?)(?:\.git)?/?$#';

    /** Même règle que pour les déploiements : rien qui puisse passer pour une option de commande. */
    private const string BRANCH_PATTERN = '#^[A-Za-z0-9][A-Za-z0-9._/-]{0,254}$#';

    private function __construct(
        public string $owner,
        public string $name,
    ) {}

    /**
     * Renvoie null si l'adresse n'est pas celle d'un dépôt GitHub.
     */
    public static function fromUrl(string $url): ?self
    {
        if (preg_match(self::URL_PATTERN, trim($url), $matches) !== 1) {
            return null;
        }

        // « . » et « .. » ne sont pas des noms de dépôt : ils serviraient à remonter dans une adresse.
        if (in_array($matches[2], ['.', '..'], true)) {
            return null;
        }

        return new self($matches[1], $matches[2]);
    }

    public static function fromParts(string $owner, string $name): self
    {
        return new self($owner, $name);
    }

    public static function isValidBranch(string $branch): bool
    {
        return preg_match(self::BRANCH_PATTERN, $branch) === 1 && ! str_contains($branch, '..');
    }

    /**
     * Adresse publique du dépôt.
     */
    public function url(): string
    {
        return "https://github.com/{$this->owner}/{$this->name}";
    }
}
