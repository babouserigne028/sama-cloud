<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Données de connexion.
 */
final class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'mot_de_passe' => ['required', 'string', 'max:255'],
            // Nom de l'appareil, pour reconnaître la session (ex. « Chrome sur Windows »).
            'appareil' => ['nullable', 'string', 'max:60'],
        ];
    }
}
