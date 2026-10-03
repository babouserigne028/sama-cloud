<?php

declare(strict_types=1);

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres de la liste des questions.
 */
final class SearchQuestionsRequest extends FormRequest
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
            // Texte cherché dans le titre et le contenu.
            'q' => ['nullable', 'string', 'max:100'],
            // Slug d'une technologie (ex. laravel).
            'technologie' => ['nullable', 'string', 'max:40'],
            // « resolue » = une réponse a été acceptée ; « non_resolue » = pas encore.
            'statut' => ['nullable', 'string', 'in:resolue,non_resolue'],
            // Nombre de questions par page (20 par défaut, 50 au plus).
            'par_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * Traduit le filtre « statut » : true = résolues, false = non résolues, null = toutes.
     */
    public function resolved(): ?bool
    {
        return match ($this->input('statut')) {
            'resolue' => true,
            'non_resolue' => false,
            default => null,
        };
    }
}
