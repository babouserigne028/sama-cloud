<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Décision d'un administrateur sur un signalement.
 */
final class ResolveReportRequest extends FormRequest
{
    /**
     * Le rôle administrateur est déjà vérifié par la route (contrôle « admin »).
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
            // « retirer » = le contenu est supprimé ; « rejeter » = il reste en ligne.
            'decision' => ['required', 'string', 'in:retirer,rejeter'],
        ];
    }
}
