<?php

declare(strict_types=1);

use App\Domain\Showcase\FileFilter;
use App\Domain\Showcase\GitHubRepository;

/*
 * Règles pures de la vitrine : reconnaître un dépôt GitHub et trier les fichiers.
 */

test('une adresse de dépôt GitHub est reconnue et réduite au compte et au nom', function (string $url, string $owner, string $name) {
    $repository = GitHubRepository::fromUrl($url);

    expect($repository?->owner)->toBe($owner)
        ->and($repository?->name)->toBe($name)
        ->and($repository?->url())->toBe("https://github.com/{$owner}/{$name}");
})->with([
    'simple' => ['https://github.com/awa-diop/laravel-wave', 'awa-diop', 'laravel-wave'],
    'avec .git' => ['https://github.com/awa-diop/laravel-wave.git', 'awa-diop', 'laravel-wave'],
    'avec / final' => ['https://github.com/awa-diop/laravel-wave/', 'awa-diop', 'laravel-wave'],
    'avec espaces autour' => ['  https://github.com/laravel/framework  ', 'laravel', 'framework'],
    'nom avec point et tiret bas' => ['https://github.com/vercel/next.js_demo', 'vercel', 'next.js_demo'],
]);

test('tout ce qui n\'est pas un dépôt GitHub est refusé', function (string $url) {
    expect(GitHubRepository::fromUrl($url))->toBeNull();
})->with([
    'autre site' => ['https://gitlab.com/awa/projet'],
    'faux GitHub' => ['https://github.com.pirate.io/awa/projet'],
    'sous-domaine' => ['https://evil.github.com/awa/projet'],
    'sans https' => ['http://github.com/awa/projet'],
    'adresse interne' => ['https://127.0.0.1/awa/projet'],
    'identifiants dans l\'adresse' => ['https://user:pass@github.com/awa/projet'],
    'dossier du dépôt' => ['https://github.com/awa/projet/tree/main/src'],
    'compte seul' => ['https://github.com/awa'],
    'remontée de dossier' => ['https://github.com/awa/..'],
    'paramètres' => ['https://github.com/awa/projet?tab=readme'],
    'compte commençant par un tiret' => ['https://github.com/-awa/projet'],
    'vide' => [''],
]);

test('un nom de branche ne peut pas ressembler à une option de commande', function (string $branch, bool $valid) {
    expect(GitHubRepository::isValidBranch($branch))->toBe($valid);
})->with([
    ['main', true],
    ['feature/paiement-wave', true],
    ['v1.2.3', true],
    ['--upload-pack=x', false],
    ['main; rm -rf /', false],
    ['a/../b', false],
    ['', false],
]);

test('les fichiers de code sont gardés', function (string $path) {
    expect(FileFilter::accepts($path))->toBeTrue();
})->with([
    'README.md', 'app/Models/User.php', 'src/main.ts', 'Dockerfile', '.gitignore', '.env.example',
    'bin/console', 'docs/guide.md', 'config/services.yaml', 'styles/app.css',
]);

test('les secrets, les dépendances, les binaires et les chemins dangereux sont écartés', function (string $path) {
    expect(FileFilter::accepts($path))->toBeFalse();
})->with([
    'secret' => ['.env'],
    'secret dans un sous-dossier' => ['backend/.env'],
    'secret de production' => ['.env.production'],
    'secret en majuscules' => ['.ENV'],
    'clé privée' => ['deploy/id_rsa'],
    'certificat' => ['certs/server.pem'],
    'dépendances Node' => ['node_modules/lodash/index.js'],
    'dépendances PHP' => ['vendor/autoload.php'],
    'dépendances dans un sous-projet' => ['frontend/node_modules/react/index.js'],
    'dossier git' => ['.git/config'],
    'dossier généré' => ['dist/app.js'],
    'image' => ['public/logo.png'],
    'archive' => ['backup.zip'],
    'fichier minifié' => ['public/app.min.js'],
    'verrouillage' => ['package-lock.json'],
    'remontée de dossier' => ['../etc/passwd'],
    'remontée au milieu' => ['src/../../etc/passwd'],
    'chemin absolu' => ['/etc/passwd'],
    'antislash' => ['src\\app.php'],
    'chemin vide' => [''],
    'double barre' => ['src//app.php'],
    'caractère de contrôle' => ["src/app\x00.php"],
]);

test('un contenu binaire n\'est pas pris pour du texte', function () {
    expect(FileFilter::isText("<?php\n\necho 'Bonjour à tous';\n"))->toBeTrue()
        ->and(FileFilter::isText("PK\x03\x04\x00\x00binaire"))->toBeFalse()
        ->and(FileFilter::isText("\xFF\xFE\xFD"))->toBeFalse()
        ->and(FileFilter::isText(''))->toBeTrue();
});
