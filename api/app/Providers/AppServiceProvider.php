<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Showcase\Contracts\RepositorySource;
use App\Http\OpenApi\DocumentTransformer;
use App\Http\OpenApi\ErrorResponsesTransformer;
use App\Infrastructure\Showcase\GitHubRepositorySource;
use App\Models\PersonalAccessToken;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // La vitrine lit les dépôts chez GitHub. Les tests remplacent cette source par une fausse.
        $this->app->bind(RepositorySource::class, GitHubRepositorySource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureSafetyNets();
        $this->configureAuthentication();
        $this->configureRateLimiting();
        $this->configureApiDocumentation();
    }

    /**
     * Garde-fous qui font échouer tôt les erreurs de programmation.
     */
    private function configureSafetyNets(): void
    {
        // Hors production : erreur immédiate en cas de chargement paresseux (N+1),
        // d'attribut inexistant ou d'attribut non autorisé ignoré en silence.
        Model::shouldBeStrict(! $this->app->isProduction());

        // En production : interdit les commandes qui effacent la base (migrate:fresh, db:wipe…).
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Les dates ne sont jamais modifiées « sur place » : chaque calcul crée une nouvelle date.
        Date::use(CarbonImmutable::class);
    }

    /**
     * Jetons d'accès et règles des mots de passe.
     */
    private function configureAuthentication(): void
    {
        // Sanctum utilise notre modèle de jeton, qui ajoute le type (session ou agent IA) et le plafond.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Règle commune à tous les mots de passe : 10 caractères au moins, avec lettres et chiffres.
        // En production, on refuse aussi les mots de passe connus dans des fuites de données.
        Password::defaults(function (): Password {
            $rule = Password::min(10)->letters()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Documentation OpenAPI, générée à partir du code (routes, requêtes, ressources).
     */
    private function configureApiDocumentation(): void
    {
        Scramble::configure()
            ->withOperationTransformers(ErrorResponsesTransformer::class)
            ->withDocumentTransformers(DocumentTransformer::class);
    }

    /**
     * Limites de débit : évitent qu'une IA en boucle ou un abus sature le service.
     */
    private function configureRateLimiting(): void
    {
        // Toute l'API. Cette limite est vérifiée AVANT l'authentification (voir bootstrap/app.php),
        // pour que les appels avec un jeton faux ou absent soient comptés eux aussi.
        RateLimiter::for('api', function (Request $request): array {
            $perMinute = config()->integer('samacloud.api.requests_per_minute');
            $bearerToken = $request->bearerToken();

            // Sans jeton : un compteur par adresse IP.
            if ($bearerToken === null) {
                return [Limit::perMinute($perMinute)->by('ip:'.$request->ip())];
            }

            return [
                // Plafond large par adresse IP : bloque celui qui essaie des jetons au hasard.
                Limit::perMinute(config()->integer('samacloud.api.requests_per_minute_per_ip'))
                    ->by('ip-avec-jeton:'.$request->ip()),
                // Un compteur par jeton : l'IA en boucle ne bloque pas l'humain, et inversement.
                // La clé est l'empreinte du jeton : sa valeur n'est jamais gardée en mémoire cache.
                Limit::perMinute($perMinute)->by('jeton:'.hash('sha256', $bearerToken)),
            ];
        });

        // Publications dans la communauté (questions, réponses, votes) : par compte, contre le spam.
        RateLimiter::for('publication', function (Request $request): Limit {
            // La limite de débit passe avant l'authentification : on identifie donc le compte ici,
            // à partir de son jeton. Sans jeton valide, le compteur est celui de l'adresse IP.
            $account = $request->user('sanctum')?->getAuthIdentifier();

            return Limit::perMinute(config()->integer('samacloud.community.posts_per_minute'))
                ->by($account !== null ? 'publication:compte:'.$account : 'publication:ip:'.$request->ip());
        });

        // Connexion et inscription : très peu d'essais par minute pour une même adresse e-mail
        // depuis une même adresse IP, afin de bloquer la recherche de mots de passe.
        RateLimiter::for('auth', function (Request $request): Limit {
            $email = Str::lower($request->string('email')->toString());

            return Limit::perMinute(config()->integer('samacloud.auth.attempts_per_minute'))
                ->by('auth:'.$email.'|'.$request->ip());
        });
    }
}
