<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Application\Community\Queries\SearchProfiles;
use App\Domain\Community\Enums\Availability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\SearchProfilesRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Communauté — profils', weight: 5)]
final class ProfileController extends Controller
{
    /**
     * Rechercher des profils.
     *
     * Recherche publique par texte, pays, technologie et disponibilité, 20 profils par page.
     * Les comptes suspendus n'apparaissent pas.
     *
     * @unauthenticated
     */
    public function index(SearchProfilesRequest $request, SearchProfiles $searchProfiles): AnonymousResourceCollection
    {
        $profiles = $searchProfiles->handle(
            search: $request->filled('q') ? $request->string('q')->trim()->toString() : null,
            countryCode: $request->filled('pays') ? $request->string('pays')->toString() : null,
            technologySlug: $request->filled('technologie') ? $request->string('technologie')->toString() : null,
            availability: $request->filled('disponibilite') ? Availability::from($request->string('disponibilite')->toString()) : null,
            perPage: $request->integer('par_page', 20),
        );

        return ProfileResource::collection($profiles);
    }

    /**
     * Voir un profil public.
     *
     * @unauthenticated
     *
     * @param  string  $pseudo  Pseudo du développeur (ex. awa-diop).
     */
    public function show(string $pseudo): ProfileResource
    {
        // Le profil d'un compte suspendu est « introuvable ».
        $profile = Profile::query()
            ->where('username', $pseudo)
            ->whereHas('user', fn (Builder $user) => $user->whereNull('suspended_at'))
            ->with(['user', 'technologies'])
            ->firstOrFail();

        return new ProfileResource($profile);
    }
}
