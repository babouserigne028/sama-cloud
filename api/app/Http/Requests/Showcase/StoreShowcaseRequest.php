<?php

declare(strict_types=1);

namespace App\Http\Requests\Showcase;

use App\Domain\Showcase\GitHubRepository;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Présentation d'un nouveau projet dans la vitrine.
 */
final class StoreShowcaseRequest extends FormRequest
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
            'titre' => ['required', 'string', 'min:3', 'max:100'],
            // Présentation du projet, en Markdown.
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            // Adresse d'un dépôt GitHub public, de la forme https://github.com/compte/depot.
            'depot' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || GitHubRepository::fromUrl($value) === null) {
                    $fail('Le dépôt doit être une adresse GitHub de la forme https://github.com/compte/depot.');
                }
            }],
            // Branche à importer. Absente = la branche par défaut du dépôt.
            'branche' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! GitHubRepository::isValidBranch($value)) {
                    $fail('Le nom de la branche contient des caractères non autorisés.');
                }
            }],
            // Adresse de la version en ligne (« Voir la démo »), en HTTPS.
            'demo_url' => ['nullable', 'string', 'max:255', 'url:https'],
            'technologies' => ['required', 'array', 'min:1', 'max:8'],
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
     * Le dépôt validé, sous forme d'objet du domaine.
     */
    public function repository(): GitHubRepository
    {
        /** @var GitHubRepository $repository La règle « depot » garantit que l'adresse est valide. */
        $repository = GitHubRepository::fromUrl($this->string('depot')->toString());

        return $repository;
    }
}
