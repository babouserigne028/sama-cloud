<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Community\Enums\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Profil public d'un développeur. Il ne contient aucune donnée sensible :
 * l'e-mail, le mot de passe et le rôle restent dans le compte (User).
 *
 * @property int $user_id
 * @property string $username
 * @property string|null $headline
 * @property string|null $bio
 * @property Availability $availability
 * @property string|null $github_url
 * @property string|null $website_url
 * @property string|null $linkedin_url
 * @property CarbonImmutable $created_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'username', 'headline', 'bio', 'availability', 'github_url', 'website_url', 'linkedin_url'])]
class Profile extends Model
{
    /**
     * La clé du profil est l'identifiant du compte : un profil par compte.
     */
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'availability' => Availability::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Compétences affichées sur le profil.
     *
     * @return BelongsToMany<Technology, $this>
     */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class, 'profile_technology', 'profile_user_id', 'technology_id');
    }
}
