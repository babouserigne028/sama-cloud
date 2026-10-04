<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Application\Showcase\Contracts\RepositorySource;
use App\Application\Showcase\Data\ImportedFiles;
use App\Domain\Showcase\Exceptions\RepositoryImportFailed;
use App\Domain\Showcase\GitHubRepository;

/**
 * Fausse source de dépôt pour les tests : aucun appel à GitHub.
 * Elle renvoie les fichiers qu'on lui donne, ou échoue avec la raison demandée.
 */
final class FakeRepositorySource implements RepositorySource
{
    /** @var list<array{depot: string, branche: string|null}> Demandes reçues, pour les vérifier dans les tests. */
    public array $requests = [];

    /**
     * @param  array<string, string>  $files
     */
    public function __construct(
        public array $files = ['README.md' => "# Projet de test\n", 'src/app.php' => "<?php\n\necho 'ok';\n"],
        public bool $truncated = false,
        public ?string $failure = null,
    ) {}

    public function fetch(GitHubRepository $repository, ?string $branch): ImportedFiles
    {
        $this->requests[] = ['depot' => $repository->url(), 'branche' => $branch];

        if ($this->failure !== null) {
            throw new RepositoryImportFailed($this->failure);
        }

        return new ImportedFiles($this->files, $this->truncated);
    }
}
