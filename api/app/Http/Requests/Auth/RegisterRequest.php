<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Données d'inscription d'un développeur.
 */
final class RegisterRequest extends FormRequest
{
    /**
     * L'inscription est ouverte à tous.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * L'adresse est mise en minuscules et le pays en majuscules avant la vérification,
     * pour que « Awa@X.sn » et « awa@x.sn » soient bien vus comme la même adresse.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $country = $this->input('pays');

        $this->merge([
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
            'pays' => is_string($country) ? Str::upper(trim($country)) : $country,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            // « confirmed » : le champ mot_de_passe_confirmation doit être identique.
            'mot_de_passe' => ['required', 'string', 'confirmed', Password::defaults()],
            // Code ISO du pays sur 2 lettres (ex. SN, CI, ML), facultatif.
            'pays' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Un compte existe déjà avec cette adresse e-mail.',
            'pays.regex' => 'Le pays doit être un code à 2 lettres, par exemple SN.',
        ];
    }
}
