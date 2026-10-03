<?php

declare(strict_types=1);

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Choix de la réponse acceptée.
 */
final class AcceptAnswerRequest extends FormRequest
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
            // Identifiant d'une réponse de cette question.
            'reponse_id' => ['required', 'string', 'ulid'],
        ];
    }
}
