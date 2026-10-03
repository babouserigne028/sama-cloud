<?php

declare(strict_types=1);

namespace App\Http\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer as DocumentTransformerContract;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Dernière passe sur le document OpenAPI complet :
 *  - l'authentification par jeton (Authorization: Bearer …) ;
 *  - le schéma « Erreur » { code, message, details? }, utilisé par toutes les réponses d'erreur ;
 *  - une adresse de serveur relative, pour que le document soit le même sur toutes les machines.
 */
final class DocumentTransformer implements DocumentTransformerContract
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $document->secure(
            SecurityScheme::http('bearer')
                ->as('jeton')
                ->setDescription('Jeton de session (`sc_sess_…`) ou jeton d\'agent IA (`sc_live_…`, `sc_test_…`).'),
        );

        $document->servers = [
            (new Server('/api'))->setDescription('Serveur qui héberge cette documentation'),
        ];

        $errorSchema = $document->components->addSchema('Erreur', Schema::fromType($this->errorType()));

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach ($operation->responses ?? [] as $response) {
                    if ($response instanceof Response && (int) $response->code >= 400) {
                        $response->setContent('application/json', $errorSchema);
                    }
                }
            }
        }

        // Les réponses d'erreur au format par défaut de Laravel ne sont plus utilisées.
        $document->components->responses = [];
    }

    /**
     * Format commun de toutes les erreurs de l'API (voir ApiErrorRenderer).
     */
    private function errorType(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('code', (new StringType)->setDescription('Identifiant stable de l\'erreur, à lire par les programmes.')->example('quota_atteint'))
            ->addProperty('message', (new StringType)->setDescription('Explication en français, affichable telle quelle.'))
            ->addProperty('details', (new MixedType)->setDescription('Précisions éventuelles. Pour une erreur de validation : liste de { champ, message }.'))
            ->setRequired(['code', 'message']);
    }
}
