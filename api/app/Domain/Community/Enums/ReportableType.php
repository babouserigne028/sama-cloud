<?php

declare(strict_types=1);

namespace App\Domain\Community\Enums;

/**
 * Types de contenus qu'un membre peut signaler.
 */
enum ReportableType: string
{
    case Question = 'question';
    case Answer = 'reponse';
}
