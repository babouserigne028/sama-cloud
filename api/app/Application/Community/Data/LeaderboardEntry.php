<?php

declare(strict_types=1);

namespace App\Application\Community\Data;

use App\Models\Profile;

/**
 * Ligne du classement : un développeur, son total de points et son rang.
 */
final readonly class LeaderboardEntry
{
    public function __construct(
        public int $rank,
        public int $points,
        public Profile $profile,
    ) {}
}
