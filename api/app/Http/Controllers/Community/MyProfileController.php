<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Application\Community\Actions\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\OpenApi\ApiError;
use App\Http\Requests\Community\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;

#[Group('Communauté — profils', weight: 5)]
final class MyProfileController extends Controller
{
    /**
     * Voir son propre profil public.
     */
    public function show(#[CurrentUser] User $user): ProfileResource
    {
        return new ProfileResource($this->profileOf($user));
    }

    /**
     * Modifier son profil public.
     *
     * Seuls les champs envoyés sont modifiés. « technologies » remplace toute la liste des compétences.
     */
    #[ApiError(409, 'pseudo_deja_pris', 'Le pseudo vient d\'être pris par un autre développeur.')]
    public function update(UpdateProfileRequest $request, #[CurrentUser] User $user, UpdateProfile $updateProfile): ProfileResource
    {
        $updateProfile->handle($user, $request->changes());

        return new ProfileResource($this->profileOf($user));
    }

    private function profileOf(User $user): Profile
    {
        return Profile::query()->whereKey($user->id)->with(['user', 'technologies'])->firstOrFail();
    }
}
