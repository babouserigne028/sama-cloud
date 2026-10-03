<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Resources\TechnologyResource;
use App\Models\Technology;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Communauté — profils', weight: 5)]
final class TechnologyController extends Controller
{
    /**
     * Lister les technologies.
     *
     * Liste de référence, par ordre alphabétique. Leurs « slug » servent aux compétences
     * d'un profil et aux filtres de recherche.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return TechnologyResource::collection(Technology::query()->orderBy('name')->orderBy('id')->get());
    }
}
