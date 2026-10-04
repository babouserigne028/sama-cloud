<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Showcase;
use App\Models\ShowcaseComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowcaseComment>
 */
class ShowcaseCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'showcase_id' => Showcase::factory(),
            'user_id' => User::factory(),
            'body' => 'Très utile, merci pour le partage !',
        ];
    }
}
