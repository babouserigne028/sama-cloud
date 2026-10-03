<?php

declare(strict_types=1);

namespace App\Http\Requests\Community;

use App\Domain\Community\Enums\Availability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Filtres de la recherche de profils.
 */
final class SearchProfilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $country = $this->query('pays');

        if (is_string($country)) {
            $this->merge(['pays' => Str::upper(trim($country))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Texte cherché dans le nom, le pseudo et la phrase de présentation.
            'q' => ['nullable', 'string', 'max:100'],
            // Code pays sur 2 lettres (ex. SN).
            'pays' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            // Slug d'une technologie (ex. laravel).
            'technologie' => ['nullable', 'string', 'max:40'],
            'disponibilite' => ['nullable', Rule::enum(Availability::class)],
            // Nombre de profils par page (20 par défaut, 50 au plus).
            'par_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
