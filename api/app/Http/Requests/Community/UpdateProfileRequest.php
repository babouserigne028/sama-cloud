<?php

declare(strict_types=1);

namespace App\Http\Requests\Community;

use App\Domain\Community\Enums\Availability;
use App\Domain\Community\UsernameRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Modification de son propre profil public. Tous les champs sont facultatifs :
 * seuls ceux qui sont envoyés sont modifiés.
 */
final class UpdateProfileRequest extends FormRequest
{
    /**
     * Correspondance entre les champs de l'API (français) et ceux du code.
     */
    private const array FIELDS = [
        'nom' => 'name',
        'pays' => 'country_code',
        'pseudo' => 'username',
        'titre' => 'headline',
        'bio' => 'bio',
        'disponibilite' => 'availability',
        'github' => 'github_url',
        'site' => 'website_url',
        'linkedin' => 'linkedin_url',
        'technologies' => 'technologies',
    ];

    /**
     * Le droit de modifier le profil est déjà vérifié par la route (capacité « profil:modifier »).
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalised = [];

        if (is_string($this->input('pseudo'))) {
            $normalised['pseudo'] = Str::lower(trim($this->input('pseudo')));
        }

        if (is_string($this->input('pays'))) {
            $normalised['pays'] = Str::upper(trim($this->input('pays')));
        }

        $this->merge($normalised);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'nom' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'pays' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            // Minuscules, chiffres et tirets, 3 à 24 caractères.
            'pseudo' => [
                'sometimes', 'required', 'string',
                'regex:'.UsernameRules::PATTERN,
                Rule::notIn(UsernameRules::RESERVED),
                Rule::unique('profiles', 'username')->ignore($this->user()?->getAuthIdentifier(), 'user_id'),
            ],
            'titre' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'disponibilite' => ['sometimes', 'required', Rule::enum(Availability::class)],
            // Liste complète des compétences à garder (slugs), 15 au plus.
            'technologies' => ['sometimes', 'array', 'max:15'],
            'technologies.*' => ['string', 'distinct', Rule::exists('technologies', 'slug')],
            // Liens en HTTPS uniquement : jamais de « javascript: » ni d'adresse non chiffrée.
            'github' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^https:\/\/github\.com\/[A-Za-z0-9-]+\/?$/'],
            'site' => ['sometimes', 'nullable', 'string', 'max:255', 'url:https'],
            'linkedin' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^https:\/\/([a-z]{2,3}\.)?linkedin\.com\/in\/[A-Za-z0-9\-_%]+\/?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pseudo.regex' => 'Le pseudo doit contenir 3 à 24 caractères : minuscules, chiffres et tirets.',
            'pseudo.not_in' => 'Ce pseudo est réservé.',
            'pseudo.unique' => 'Ce pseudo est déjà pris.',
            'pays.regex' => 'Le pays doit être un code à 2 lettres, par exemple SN.',
            'github.regex' => 'Le lien GitHub doit avoir la forme https://github.com/votre-compte.',
            'linkedin.regex' => 'Le lien LinkedIn doit avoir la forme https://www.linkedin.com/in/votre-profil.',
            'technologies.*.exists' => 'Cette technologie est inconnue.',
        ];
    }

    /**
     * Les champs validés, renommés pour le code (ex. « pseudo » devient « username »).
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
