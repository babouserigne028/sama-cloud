<?php

declare(strict_types=1);

namespace App\Http\Requests\Showcase;

use App\Domain\Showcase\FileFilter;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création ou modification d'un fichier dans la copie SamaCloud d'un projet.
 */
final class SaveShowcaseFileRequest extends FormRequest
{
    /**
     * Le droit de modifier ce projet précis est vérifié dans le contrôleur (ShowcasePolicy).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Les mêmes règles qu'à l'import s'appliquent : on ne peut pas ajouter à la main
     * un fichier .env, un binaire ou un chemin qui sort du projet.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $maxBytes = config()->integer('samacloud.showcase.max_file_bytes');

        return [
            // Chemin du fichier dans le projet (ex. src/app.php).
            'chemin' => ['required', 'string', 'max:400', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! FileFilter::accepts($value)) {
                    $fail('Ce chemin n\'est pas autorisé dans la vitrine (secret, binaire, dossier de dépendances ou chemin invalide).');
                }
            }],
            // Contenu complet du fichier (texte). Une chaîne vide est permise.
            'contenu' => ['present', 'string', function (string $attribute, mixed $value, Closure $fail) use ($maxBytes): void {
                if (! is_string($value)) {
                    return;
                }

                if (strlen($value) > $maxBytes) {
                    $fail("Le fichier dépasse la taille maximale de {$maxBytes} octets.");
                }

                if (! FileFilter::isText($value)) {
                    $fail('Le contenu doit être du texte.');
                }
            }],
        ];
    }

    /**
     * Les chaînes vides ne sont pas transformées en « null » pour ce formulaire :
     * un fichier vide est un contenu valide.
     */
    protected function prepareForValidation(): void
    {
        if ($this->exists('contenu') && $this->input('contenu') === null) {
            $this->merge(['contenu' => '']);
        }
    }
}
