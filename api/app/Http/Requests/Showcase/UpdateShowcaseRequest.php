<?php

declare(strict_types=1);

namespace App\Http\Requests\Showcase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification de la présentation d'un projet. Seuls les champs envoyés sont modifiés.
 * Le dépôt ne se change pas : pour un autre dépôt, on présente un autre projet.
 */
final class UpdateShowcaseRequest extends FormRequest
{
    private const array FIELDS = [
        'titre' => 'title',
        'description' => 'description',
        'demo_url' => 'demo_url',
    ];

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
            'titre' => ['sometimes', 'required', 'string', 'min:3', 'max:100'],
            'description' => ['sometimes', 'required', 'string', 'min:20', 'max:5000'],
            'demo_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url:https'],
            // Liste complète des technologies à garder.
            'technologies' => ['sometimes', 'required', 'array', 'min:1', 'max:8'],
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
            'demo_url.url' => 'L\'adresse de la démo doit commencer par https://.',
        ];
    }

    /**
     * Les champs de présentation validés, renommés pour le code.
     *
     * @return array<string, mixed>
     */
    public function attributesToUpdate(): array
    {
        $attributes = [];

        foreach ($this->validated() as $field => $value) {
            if (isset(self::FIELDS[$field])) {
                $attributes[self::FIELDS[$field]] = $value;
            }
        }

        return $attributes;
    }
}
