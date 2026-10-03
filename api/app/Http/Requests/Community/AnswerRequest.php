<?php

declare(strict_types=1);

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Contenu d'une réponse (création ou modification).
 */
final class AnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Markdown, avec blocs de code.
            'contenu' => ['required', 'string', 'min:10', 'max:20000'],
        ];
    }
}
