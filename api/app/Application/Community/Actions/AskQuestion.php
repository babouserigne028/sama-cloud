<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Models\Question;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Publie une question dans la communauté.
 */
final class AskQuestion
{
    /**
     * @param  list<string>  $technologySlugs  Technologies concernées (déjà validées).
     */
    public function handle(User $author, string $title, string $body, array $technologySlugs): Question
    {
        return DB::transaction(function () use ($author, $title, $body, $technologySlugs): Question {
            $question = Question::query()->create([
                'user_id' => $author->id,
                'title' => $title,
                'body' => $body,
            ]);

            $question->technologies()->sync(Technology::query()->whereIn('slug', $technologySlugs)->pluck('id'));

            return $question;
        });
    }
}
