<?php

declare(strict_types=1);

use App\Application\Showcase\Data\ImportedFiles;
use App\Domain\Showcase\Exceptions\RepositoryImportFailed;
use App\Domain\Showcase\GitHubRepository;
use App\Infrastructure\Showcase\GitHubRepositorySource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Lecture réelle d'une archive de dépôt : le téléchargement est simulé, mais le tri
 * et les protections tournent sur de vraies archives .zip fabriquées par le test.
 */

/**
 * Fabrique une archive .zip comme celles de GitHub : tout est dans un dossier racine.
 *
 * @param  array<string, string>  $entries  Chemin => contenu.
 */
function zipArchive(array $entries, string $root = 'laravel-wave-abc123/'): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test-'.bin2hex(random_bytes(8)).'.zip';

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($entries as $name => $content) {
        $zip->addFromString($root.$name, $content);
    }

    $zip->close();

    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

function fetchRepository(?string $branch = null): ImportedFiles
{
    return app(GitHubRepositorySource::class)->fetch(GitHubRepository::fromParts('awa-diop', 'laravel-wave'), $branch);
}

function importFailure(Closure $callback): ?string
{
    try {
        $callback();
    } catch (RepositoryImportFailed $failure) {
        return $failure->reason;
    }

    return null;
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('seul le code est gardé, sans le dossier racine de l\'archive, trié par chemin', function () {
    Http::fake(['api.github.com/*' => Http::response(zipArchive([
        'src/app.php' => "<?php\n\necho 'ok';\n",
        'README.md' => "# Laravel Wave\n",
        '.env' => 'APP_KEY=secret-a-ne-jamais-copier',
        '.env.example' => 'APP_KEY=',
        'node_modules/lodash/index.js' => 'module.exports = {};',
        'vendor/autoload.php' => '<?php',
        'public/logo.png' => "\x89PNG\r\n\x1a\n\x00\x00binaire",
        'data/export.bin.txt' => "texte\x00avec octet nul",
    ]))]);

    $imported = fetchRepository();

    expect(array_keys($imported->files))->toBe(['.env.example', 'README.md', 'src/app.php'])
        ->and($imported->files['src/app.php'])->toBe("<?php\n\necho 'ok';\n")
        ->and($imported->truncated)->toBeFalse()
        ->and($imported->totalBytes())->toBe(strlen('APP_KEY=') + strlen("# Laravel Wave\n") + strlen("<?php\n\necho 'ok';\n"));

    expect(implode('', $imported->files))->not->toContain('secret-a-ne-jamais-copier');
});

test('l\'archive est demandée à GitHub, pour le bon dépôt et la bonne branche', function (?string $branch, string $expectedUrl) {
    Http::fake(['api.github.com/*' => Http::response(zipArchive(['README.md' => '# ok']))]);

    fetchRepository($branch);

    Http::assertSent(fn (Request $request) => $request->url() === $expectedUrl && $request->method() === 'GET');
    Http::assertSentCount(1);
})->with([
    'branche par défaut' => [null, 'https://api.github.com/repos/awa-diop/laravel-wave/zipball'],
    'branche précise' => ['feature/paiement', 'https://api.github.com/repos/awa-diop/laravel-wave/zipball/feature/paiement'],
]);

test('un chemin piégé dans l\'archive ne sort jamais du dossier du projet', function () {
    Http::fake(['api.github.com/*' => Http::response(zipArchive([
        'README.md' => '# ok',
        '../../etc/passwd' => 'root:x:0:0',
        'src/../../secret.txt' => 'piège',
    ]))]);

    expect(array_keys(fetchRepository()->files))->toBe(['README.md']);
});

test('les limites de taille et de nombre de fichiers sont respectées, et signalées', function () {
    config()->set('samacloud.showcase.max_file_bytes', 100);
    config()->set('samacloud.showcase.max_files', 3);

    Http::fake(['api.github.com/*' => Http::response(zipArchive([
        'a.txt' => 'a',
        'b.txt' => 'b',
        'trop-gros.txt' => str_repeat('x', 101),
        'c.txt' => 'c',
        'd.txt' => 'd',
    ]))]);

    $imported = fetchRepository();

    expect(array_keys($imported->files))->toBe(['a.txt', 'b.txt', 'c.txt'])
        ->and($imported->truncated)->toBeTrue();
});

test('la taille totale copiée est plafonnée', function () {
    config()->set('samacloud.showcase.max_total_bytes', 25);

    Http::fake(['api.github.com/*' => Http::response(zipArchive([
        'a.txt' => str_repeat('a', 10),
        'b.txt' => str_repeat('b', 10),
        'c.txt' => str_repeat('c', 10),
    ]))]);

    $imported = fetchRepository();

    expect($imported->totalBytes())->toBe(20)->and($imported->truncated)->toBeTrue();
});

test('chaque échec connu donne une raison claire', function (Closure $fake, string $expectedReason) {
    $fake();

    expect(importFailure(fn () => fetchRepository()))->toBe($expectedReason);
})->with([
    'dépôt introuvable ou privé' => [fn () => Http::fake(['api.github.com/*' => Http::response('', 404)]), RepositoryImportFailed::NOT_FOUND],
    'GitHub en panne' => [fn () => Http::fake(['api.github.com/*' => Http::response('', 503)]), RepositoryImportFailed::NETWORK],
    'limite d\'appels atteinte' => [fn () => Http::fake(['api.github.com/*' => Http::response('', 403)]), RepositoryImportFailed::NETWORK],
    'réseau coupé' => [fn () => Http::fake(['api.github.com/*' => fn () => throw new ConnectionException('timeout')]), RepositoryImportFailed::NETWORK],
    'archive illisible' => [fn () => Http::fake(['api.github.com/*' => Http::response('ceci n\'est pas un zip')]), RepositoryImportFailed::INVALID_ARCHIVE],
    'dépôt sans code' => [fn () => Http::fake(['api.github.com/*' => Http::response(zipArchive(['logo.png' => "\x89PNG", '.env' => 'SECRET=1']))]), RepositoryImportFailed::EMPTY],
    'archive trop volumineuse' => [function () {
        config()->set('samacloud.showcase.max_archive_bytes', 50);
        Http::fake(['api.github.com/*' => Http::response(zipArchive(['README.md' => str_repeat('contenu ', 50)]))]);
    }, RepositoryImportFailed::TOO_LARGE],
]);

test('aucune archive temporaire ne reste sur le disque, même après un échec', function () {
    $before = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'samacloud-depot-*') ?: [];

    Http::fake(['api.github.com/*' => Http::sequence()
        ->push(zipArchive(['README.md' => '# ok']))
        ->push('archive cassée')]);

    fetchRepository();
    importFailure(fn () => fetchRepository());

    expect(glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'samacloud-depot-*') ?: [])->toBe($before);
});
