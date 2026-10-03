<?php

declare(strict_types=1);

namespace App\Domain\Community\Enums;

/**
 * Motif d'un signalement.
 */
enum ReportReason: string
{
    case Spam = 'spam';
    case Offensive = 'contenu_offensant';
    case OffTopic = 'hors_sujet';
    case Other = 'autre';
}
