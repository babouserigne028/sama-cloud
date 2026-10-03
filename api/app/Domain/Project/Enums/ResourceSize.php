<?php

declare(strict_types=1);

namespace App\Domain\Project\Enums;

/**
 * Taille d'un service ou d'une base, telle qu'écrite dans datacloud.yaml.
 * Les ressources exactes (processeur, mémoire) et le prix viennent du catalogue.
 */
enum ResourceSize: string
{
    case Small = 'petite';
    case Medium = 'moyenne';
    case Large = 'grande';
}
