<?php

declare(strict_types=1);

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'une question. Seuls les champs envoyés sont modifiés.
 */
final class UpdateQuestionRequest extends FormRequest
{
    private const array FIELDS = [
        'titre' => 'title',
        'contenu' => 'body',
        'technologies' => 'technologies',
    ];

    /**
     * Le droit de modifier cette question précise est vérifié dans le contrôleur (QuestionPolicy).
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
            'titre' => ['sometimes', 'required', 'string', 'min:10', 'max:150'],
            'contenu' => ['sometimes', 'required', 'string', 'min:20', 'max:20000'],
            // Liste complète des technologies à garder.
            'technologies' => ['sometimes', 'required', 'array', 'min:1', 'max:5'],
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

    /**
     * Les champs validés, renommés pour le code.
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = [];

        foreach ($this->validated() as $field => $value) {
            if (isset(self::FIELDS[$field])) {
                $changes[self::FIELDS[$field]] = $value;
            }
        }

        return $changes;
    }
}
