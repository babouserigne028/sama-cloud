<?php

declare(strict_types=1);

use App\Domain\Community\Enums\Availability;
use App\Domain\Community\UsernameRules;
use App\Models\Profile;
use App\Models\Technology;
use App\Models\User;
use Database\Seeders\TechnologySeeder;

/*
 * Profils publics : la vitrine de chaque développeur dans la communauté.
 */

function developer(array $account = [], array $profile = [], array $technologies = []): User
{
    $user = User::factory()->create($account);

    if ($profile !== []) {
        $user->profile()->update($profile);
    }

    if ($technologies !== []) {
        $ids = collect($technologies)->map(
            fn (string $slug) => Technology::query()->firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id,
        );
        Profile::query()->findOrFail($user->id)->technologies()->sync($ids);
    }

    return $user;
}

// --- Création du profil ---

test('l\'inscription crée un profil public avec un pseudo tiré du nom', function () {
    $this->postJson('/api/inscription', [
        'nom' => 'Awa Diop',
        'email' => 'awa@exemple.sn',
        'mot_de_passe' => 'motdepasse-solide-2026',
        'mot_de_passe_confirmation' => 'motdepasse-solide-2026',
        'pays' => 'SN',
    ])->assertCreated();

    $profile = Profile::query()->sole();

    expect($profile->username)->toBe('awa-diop')
        ->and($profile->availability)->toBe(Availability::OpenToOffers);

    $this->getJson('/api/profils/awa-diop')->assertOk()->assertJsonPath('data.nom', 'Awa Diop');
});

test('deux développeurs du même nom reçoivent deux pseudos différents et valides', function () {
    $first = User::factory()->create(['name' => 'Awa Diop']);
    $second = User::factory()->create(['name' => 'Awa Diop']);

    expect($first->profile->username)->toBe('awa-diop')
        ->and($second->profile->username)->toStartWith('awa-diop-')
        ->and($second->profile->username)->toMatch('/^[a-z0-9][a-z0-9-]{1,22}[a-z0-9]$/');
});

test('un nom inhabituel donne quand même un pseudo valide', function (string $name) {
    $user = User::factory()->create(['name' => $name]);

    // Valide = bon format ET pas un pseudo réservé (« admin », « api »…).
    expect(UsernameRules::isValid($user->profile->username))->toBeTrue($user->profile->username);
})->with([
    'nom réservé' => ['Admin'],
    'autre nom réservé' => ['Support'],
    'trop court' => ['Al'],
    'très long' => ['Mamadou Lamine Abdoulaye Souleymane Ndiaye'],
    'sans lettre latine' => ['محمد'],
    'accents et apostrophe' => ["N'Dèye Émilie"],
]);

// --- Lecture publique ---

test('un profil public est lisible sans jeton et ne révèle jamais l\'e-mail', function () {
    developer(
        ['name' => 'Awa Diop', 'email' => 'awa@exemple.sn', 'country_code' => 'SN'],
        ['username' => 'awa', 'headline' => 'Développeuse Laravel', 'bio' => 'Dakar.', 'availability' => Availability::Available, 'github_url' => 'https://github.com/awa'],
        ['laravel', 'postgresql'],
    );

    $response = $this->getJson('/api/profils/awa');

    $response->assertOk()->assertJson(['data' => [
        'pseudo' => 'awa',
        'nom' => 'Awa Diop',
        'titre' => 'Développeuse Laravel',
        'bio' => 'Dakar.',
        'pays' => 'SN',
        'disponibilite' => 'disponible',
        'liens' => ['github' => 'https://github.com/awa', 'site' => null, 'linkedin' => null],
    ]]);

    expect(collect($response->json('data.technologies'))->pluck('slug')->all())->toEqualCanonicalizing(['laravel', 'postgresql'])
        ->and($response->getContent())->not->toContain('awa@exemple.sn')
        ->and($response->json('data'))->not->toHaveKeys(['email', 'role', 'credit_fcfa', 'id', 'user_id']);
});

test('un pseudo inconnu, mal formé ou celui d\'un compte suspendu est introuvable', function (string $username) {
    developer(['suspended_at' => now()], ['username' => 'suspendu']);

    $this->getJson("/api/profils/{$username}")->assertStatus(404)->assertJsonPath('code', 'introuvable');
})->with(['inconnu', 'suspendu', 'Pseudo_Invalide', 'ab']);

// --- Recherche ---

test('la recherche trouve par nom, par pseudo et par phrase de présentation, sans tenir compte de la casse', function (string $search) {
    developer(['name' => 'Awa Diop'], ['username' => 'awa', 'headline' => 'Développeuse Laravel']);
    developer(['name' => 'Fatou Traoré'], ['username' => 'fatou-dev', 'headline' => 'Mobile Flutter']);
    developer(['name' => 'Moussa Koné'], ['username' => 'moussa', 'headline' => 'Sécurité']);

    $response = $this->getJson('/api/profils?q='.urlencode($search));

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
})->with([
    'par nom' => ['fatou tra'],
    'par pseudo' => ['FATOU-DEV'],
    'par présentation' => ['flutter'],
]);

test('les filtres pays, technologie et disponibilité se combinent', function () {
    developer(['country_code' => 'SN'], ['username' => 'awa', 'availability' => Availability::Available], ['laravel']);
    developer(['country_code' => 'SN'], ['username' => 'omar', 'availability' => Availability::Unavailable], ['laravel']);
    developer(['country_code' => 'CI'], ['username' => 'moussa', 'availability' => Availability::Available], ['laravel']);
    developer(['country_code' => 'SN'], ['username' => 'fatou', 'availability' => Availability::Available], ['flutter']);

    $this->getJson('/api/profils?pays=sn')->assertJsonCount(3, 'data');
    $this->getJson('/api/profils?technologie=laravel')->assertJsonCount(3, 'data');
    $this->getJson('/api/profils?disponibilite=disponible')->assertJsonCount(3, 'data');

    $this->getJson('/api/profils?pays=SN&technologie=laravel&disponibilite=disponible')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.pseudo', 'awa');
});

test('les comptes suspendus n\'apparaissent pas dans la recherche', function () {
    developer([], ['username' => 'awa']);
    developer(['suspended_at' => now()], ['username' => 'suspendu']);

    $this->getJson('/api/profils')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.pseudo', 'awa');
});

test('les caractères spéciaux de la recherche sont pris au pied de la lettre', function (string $search) {
    developer(['name' => 'Awa Diop'], ['username' => 'awa']);
    developer(['name' => 'Omar Sy'], ['username' => 'omar']);

    // Sans protection, « % » ou « _ » renverraient tous les profils.
    $this->getJson('/api/profils?q='.urlencode($search))->assertOk()->assertJsonCount(0, 'data');
})->with(['%', '_', 'a%a', "'; DROP TABLE profiles; --"]);

test('la recherche est paginée et refuse des filtres invalides', function () {
    foreach (range(1, 3) as $index) {
        developer();
    }

    $this->getJson('/api/profils?par_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/profils?par_page=51')->assertStatus(422);
    $this->getJson('/api/profils?disponibilite=peut-etre')->assertStatus(422);
    $this->getJson('/api/profils?pays=SEN')->assertStatus(422);
});

test('la liste des technologies est publique et triée par nom', function () {
    $this->seed(TechnologySeeder::class);

    $response = $this->getJson('/api/technologies');

    $names = collect($response->json('data'))->pluck('nom');

    $response->assertOk();

    expect($names->count())->toBeGreaterThan(40)
        ->and($names->all())->toBe($names->sort(SORT_STRING)->values()->all())
        ->and(collect($response->json('data'))->pluck('slug'))->toContain('laravel', 'wave', 'orange-money');

    // Relancer le seeder ne crée pas de doublon.
    $this->seed(TechnologySeeder::class);
    expect(Technology::query()->count())->toBe($names->count());
});

// --- Modification de son profil ---

test('un développeur modifie son profil, ses liens et ses compétences', function () {
    $user = developer(['name' => 'Awa Diop'], [], ['php']);
    Technology::factory()->create(['slug' => 'laravel', 'name' => 'Laravel']);
    Technology::factory()->create(['slug' => 'angular', 'name' => 'Angular']);

    $response = $this->withToken(sessionTokenFor($user))->patchJson('/api/moi/profil', [
        'nom' => 'Awa Diop Ndiaye',
        'pays' => 'ci',
        'pseudo' => 'Awa-Dev',
        'titre' => 'Développeuse full-stack',
        'bio' => 'Laravel et Angular.',
        'disponibilite' => 'disponible',
        'technologies' => ['laravel', 'angular'],
        'github' => 'https://github.com/awa-dev',
        'site' => 'https://awa.dev',
        'linkedin' => 'https://www.linkedin.com/in/awa-diop',
    ]);

    $response->assertOk()->assertJson(['data' => [
        'pseudo' => 'awa-dev',
        'nom' => 'Awa Diop Ndiaye',
        'pays' => 'CI',
        'titre' => 'Développeuse full-stack',
        'disponibilite' => 'disponible',
        'liens' => ['github' => 'https://github.com/awa-dev', 'site' => 'https://awa.dev', 'linkedin' => 'https://www.linkedin.com/in/awa-diop'],
    ]]);

    // La liste des compétences est remplacée : « php » a disparu.
    expect(collect($response->json('data.technologies'))->pluck('slug')->all())->toEqualCanonicalizing(['laravel', 'angular']);

    $this->getJson('/api/profils/awa-dev')->assertOk();
});

test('seuls les champs envoyés sont modifiés', function () {
    $user = developer(['name' => 'Awa Diop'], ['username' => 'awa', 'headline' => 'Ancien titre', 'bio' => 'Ma bio'], ['php']);

    $this->withToken(sessionTokenFor($user))->patchJson('/api/moi/profil', ['titre' => 'Nouveau titre'])
        ->assertOk()
        ->assertJsonPath('data.titre', 'Nouveau titre')
        ->assertJsonPath('data.bio', 'Ma bio')
        ->assertJsonPath('data.pseudo', 'awa')
        ->assertJsonPath('data.nom', 'Awa Diop')
        ->assertJsonCount(1, 'data.technologies');
});

test('un champ peut être vidé, et la liste des compétences aussi', function () {
    $user = developer([], ['bio' => 'Ma bio', 'github_url' => 'https://github.com/awa'], ['php']);

    $this->withToken(sessionTokenFor($user))->patchJson('/api/moi/profil', ['bio' => null, 'github' => null, 'technologies' => []])
        ->assertOk()
        ->assertJsonPath('data.bio', null)
        ->assertJsonPath('data.liens.github', null)
        ->assertJsonCount(0, 'data.technologies');
});

test('on ne peut pas prendre le pseudo d\'un autre, mais on peut renvoyer le sien', function () {
    developer([], ['username' => 'fatou']);
    $user = developer([], ['username' => 'awa']);
    $token = sessionTokenFor($user);

    $this->withToken($token)->patchJson('/api/moi/profil', ['pseudo' => 'FATOU'])
        ->assertStatus(422)
        ->assertJsonPath('details.0.message', 'Ce pseudo est déjà pris.');

    $this->withToken($token)->patchJson('/api/moi/profil', ['pseudo' => 'awa'])->assertOk();

    expect($user->profile()->sole()->username)->toBe('awa');
});

test('des données de profil invalides sont refusées et rien n\'est modifié', function (array $payload, string $invalidField) {
    $user = developer(['name' => 'Awa Diop'], ['username' => 'awa']);
    Technology::factory()->create(['slug' => 'laravel']);

    $response = $this->withToken(sessionTokenFor($user))->patchJson('/api/moi/profil', ['titre' => 'Ne doit pas passer', ...$payload]);

    $response->assertStatus(422);

    expect(collect($response->json('details'))->pluck('champ')->all())->toContain($invalidField)
        ->and($user->profile()->sole()->headline)->toBeNull();
})->with([
    'pseudo réservé' => [['pseudo' => 'admin'], 'pseudo'],
    'pseudo avec espace' => [['pseudo' => 'awa diop'], 'pseudo'],
    'pseudo trop court' => [['pseudo' => 'ab'], 'pseudo'],
    'pseudo finissant par un tiret' => [['pseudo' => 'awa-'], 'pseudo'],
    'nom vide' => [['nom' => ''], 'nom'],
    'disponibilité inconnue' => [['disponibilite' => 'peut-etre'], 'disponibilite'],
    'technologie inconnue' => [['technologies' => ['laravel', 'cobol-1959']], 'technologies.1'],
    'technologie en double' => [['technologies' => ['laravel', 'laravel']], 'technologies.0'],
    'trop de technologies' => [['technologies' => array_fill(0, 16, 'laravel')], 'technologies'],
    'lien javascript' => [['site' => 'javascript:alert(1)'], 'site'],
    'lien non chiffré' => [['site' => 'http://awa.dev'], 'site'],
    'faux lien GitHub' => [['github' => 'https://github.com.pirate.io/awa'], 'github'],
    'faux lien LinkedIn' => [['linkedin' => 'https://linkedin.com.pirate.io/in/awa'], 'linkedin'],
    'bio trop longue' => [['bio' => str_repeat('a', 1001)], 'bio'],
]);

test('on ne peut pas modifier le rôle, le crédit ou l\'e-mail en passant par le profil', function () {
    $user = developer(['email' => 'awa@exemple.sn']);

    $this->withToken(sessionTokenFor($user))->patchJson('/api/moi/profil', [
        'titre' => 'Titre',
        'role' => 'administrateur',
        'credit_fcfa' => 1_000_000,
        'email' => 'pirate@exemple.sn',
        'user_id' => 999,
    ])->assertOk();

    $user->refresh();

    expect($user->isAdmin())->toBeFalse()
        ->and($user->credit_fcfa)->toBe(0)
        ->and($user->email)->toBe('awa@exemple.sn');
});

test('modifier son profil ne touche jamais celui d\'un autre', function () {
    $other = developer([], ['username' => 'fatou', 'headline' => 'Titre de Fatou']);
    $user = developer();

    $this->withToken(sessionTokenFor($user))->patchJson('/api/moi/profil', ['titre' => 'Mon titre'])->assertOk();

    expect($other->profile()->sole()->headline)->toBe('Titre de Fatou');
});

test('un jeton IA peut lire le profil de son compte mais pas le modifier', function () {
    $user = developer([], ['username' => 'awa']);
    $agent = agentTokenFor($user);

    $this->withToken($agent)->getJson('/api/moi/profil')->assertOk()->assertJsonPath('data.pseudo', 'awa');

    $this->withToken($agent)->patchJson('/api/moi/profil', ['titre' => 'Écrit par une IA'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'capacite_manquante');

    expect($user->profile()->sole()->headline)->toBeNull();
});

test('son propre profil exige un jeton', function () {
    $this->getJson('/api/moi/profil')->assertStatus(401);
    $this->patchJson('/api/moi/profil', ['titre' => 'Titre'])->assertStatus(401);
});
