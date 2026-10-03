<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Données de création d'un jeton d'agent IA.
 */
final class StoreAgentTokenRequest extends FormRequest
{
    /**
     * Le droit de gérer les jetons est déjà vérifié par la route (capacité « jetons:gerer »).
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
        $maxDays = config()->integer('samacloud.auth.agent_token_max_days');

        return [
            // Nom libre pour reconnaître le jeton (ex. « VS Code portable »).
            'nom' => ['required', 'string', 'min:2', 'max:60'],
            'expiration_jours' => ['nullable', 'integer', 'min:1', "max:{$maxDays}"],
            // Plafond de dépense mensuel de l'IA. Absent = pas de plafond.
            'plafond_mensuel_fcfa' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }
}
