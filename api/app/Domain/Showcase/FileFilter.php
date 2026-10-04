<?php

declare(strict_types=1);

namespace App\Domain\Showcase;

/**
 * Décide quels fichiers d'un dépôt sont gardés dans la vitrine.
 *
 * On garde le code lisible. On écarte les secrets (.env), les dépendances téléchargées
 * (node_modules, vendor), les dossiers générés et les fichiers binaires.
 */
final class FileFilter
{
    /** Dossiers écartés, où qu'ils soient dans le dépôt. */
    private const array EXCLUDED_DIRECTORIES = [
        '.git', 'node_modules', 'vendor', 'bower_components',
        'dist', 'build', 'target', 'obj',
        '.next', '.nuxt', '.angular', '.svelte-kit', '.turbo', '.cache',
        '__pycache__', '.venv', 'venv', '.idea', '.vscode', '.gradle', 'coverage',
    ];

    /** Fichiers de secrets ou de clés, écartés quel que soit leur dossier. */
    private const array EXCLUDED_NAMES = [
        '.env', 'auth.json', 'id_rsa', 'id_ed25519', '.npmrc', '.pypirc', '.netrc', '.htpasswd',
        // Fichiers de verrouillage : très longs et sans intérêt à lire.
        'package-lock.json', 'pnpm-lock.yaml', 'composer.lock', 'yarn.lock',
    ];

    /** Extensions de fichiers binaires ou sans intérêt à lire. */
    private const array EXCLUDED_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'bmp', 'tiff', 'avif', 'psd',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip', 'tar', 'gz', 'tgz', 'rar', '7z', 'jar', 'war', 'phar',
        'exe', 'dll', 'so', 'dylib', 'class', 'pyc', 'o', 'a', 'wasm',
        'mp3', 'mp4', 'mov', 'avi', 'wav', 'ogg', 'webm',
        'ttf', 'otf', 'woff', 'woff2', 'eot',
        'sqlite', 'db', 'lock', 'map', 'min.js', 'min.css',
        'pem', 'key', 'p12', 'pfx', 'keystore', 'jks',
    ];

    /**
     * Le chemin est-il sûr ? Relatif, sans « .. », sans caractère de contrôle.
     */
    public static function isSafePath(string $path): bool
    {
        if ($path === '' || mb_strlen($path) > 400 || str_starts_with($path, '/') || str_contains($path, '\\')) {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * Ce fichier a-t-il sa place dans la vitrine, d'après son chemin ?
     */
    public static function accepts(string $path): bool
    {
        if (! self::isSafePath($path)) {
            return false;
        }

        $segments = explode('/', $path);
        $name = strtolower((string) array_pop($segments));

        foreach ($segments as $directory) {
            if (in_array(strtolower($directory), self::EXCLUDED_DIRECTORIES, true)) {
                return false;
            }
        }

        // « .env », « .env.local », « .env.production »… sont des secrets ; « .env.example » est un modèle.
        if (in_array($name, self::EXCLUDED_NAMES, true)) {
            return false;
        }

        if (str_starts_with($name, '.env.') && ! in_array($name, ['.env.example', '.env.sample', '.env.dist'], true)) {
            return false;
        }

        foreach (self::EXCLUDED_EXTENSIONS as $extension) {
            if (str_ends_with($name, '.'.$extension)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Le contenu est-il du texte lisible ? Un octet nul ou de l'UTF-8 invalide trahit un binaire.
     */
    public static function isText(string $content): bool
    {
        return ! str_contains($content, "\0") && mb_check_encoding($content, 'UTF-8');
    }
}
