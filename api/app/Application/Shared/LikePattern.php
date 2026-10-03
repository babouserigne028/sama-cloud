<?php

declare(strict_types=1);

namespace App\Application\Shared;

/**
 * Construit un motif de recherche « contient ce texte » pour LIKE.
 */
final class LikePattern
{
    /**
     * Les caractères spéciaux de LIKE (% et _) saisis par l'utilisateur sont neutralisés :
     * chercher « 100% » ne doit pas tout renvoyer.
     */
    public static function contains(string $text): string
    {
        return '%'.addcslashes($text, '%_\\').'%';
    }
}
