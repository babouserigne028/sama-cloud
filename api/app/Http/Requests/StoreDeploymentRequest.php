<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Données d'une demande de déploiement.
 */
final class StoreDeploymentRequest extends FormRequest
{
    /**
     * Le droit d'écrire sur les projets est déjà vérifié par la route (capacité « projets:ecrire »).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Les formats sont stricts : ces valeurs seront transmises à git par le moteur.
     * Une branche qui commencerait par « - » pourrait être prise pour une option de commande.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Branche à déployer. Absente = la branche enregistrée sur le projet.
            'branche' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', 'not_regex:/\.\./'],
            // Commit précis à déployer (7 à 40 caractères hexadécimaux). Absent = le dernier de la branche.
            'commit' => ['nullable', 'string', 'regex:/^[0-9a-f]{7,40}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branche.regex' => 'Le nom de la branche contient des caractères non autorisés.',
            'branche.not_regex' => 'Le nom de la branche contient des caractères non autorisés.',
            'commit.regex' => 'Le commit doit être un identifiant git de 7 à 40 caractères hexadécimaux.',
        ];
    }
}
