<?php

declare(strict_types=1);

namespace App\Application\Community\Actions;

use App\Models\Question;
use App\Models\Technology;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Modifie une question. Seules les clés présentes dans « $changes » sont modifiées.
 */
final class UpdateQuestion
{
    /**
     * @param  array{title?: string, body?: string, technologies?: list<string>}  $changes  Données déjà validées.
     */
    public function handle(Question $question, array $changes): Question
    {
        return DB::transaction(function () use ($question, $changes): Question {
            $question->update(Arr::only($changes, ['title', 'body']));

            if (array_key_exists('technologies', $changes)) {
                $question->technologies()->sync(
                    Technology::query()->whereIn('slug', $changes['technologies'])->pluck('id'),
                );
            }

            return $question;
        });
    }
}
