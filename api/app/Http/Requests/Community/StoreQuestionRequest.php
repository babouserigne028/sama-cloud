<?php

declare(strict_types=1);

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nouvelle question.
 */
final class StoreQuestionRequest extends FormRequest
{
    /**
     * Le droit de participer est déjà vérifié par la route (capacité « communaute:participer »).
     */
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
            'titre' => ['required', 'string', 'min:10', 'max:150'],
            // Markdown, avec blocs de code.
            'contenu' => ['required', 'string', 'min:20', 'max:20000'],
            // 1 à 5 technologies (slugs) : c'est ce qui permet de retrouver la question.
            'technologies' => ['required', 'array', 'min:1', 'max:5'],
            'technologies.*' => ['string', 'distinct', Rule::exists('technologies', 'slug')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'technologies.*.exists' => 'Cette technologie est inconnue.',
        ];
    }
}
