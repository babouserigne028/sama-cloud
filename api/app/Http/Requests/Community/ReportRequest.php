<?php

declare(strict_types=1);

namespace App\Http\Requests\Community;

use App\Domain\Community\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Signalement d'un contenu.
 */
final class ReportRequest extends FormRequest
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
            'motif' => ['required', Rule::enum(ReportReason::class)],
            // Précisions facultatives pour l'administrateur ; obligatoires si le motif est « autre ».
            'details' => ['nullable', 'required_if:motif,autre', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'details.required_if' => 'Précisez le problème quand le motif est « autre ».',
        ];
    }
}
