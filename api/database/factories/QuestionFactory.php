<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Comment intégrer le paiement Wave dans Laravel ?',
            'body' => "Je reçois une erreur 401 à l'appel de l'API.\n\n```php\nHttp::post(\$url);\n```",
        ];
    }
}
