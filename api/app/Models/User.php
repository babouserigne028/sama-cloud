<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Account\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Compte d'un développeur (client) ou d'un administrateur Systalink.
 *
 * Le rôle, le crédit, le statut « démonstration » et la suspension ne sont pas
 * remplissables en masse : ils ne changent que par du code qui le fait exprès,
 * jamais à partir des données envoyées par un formulaire.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property UserRole $role
 * @property string|null $country_code
 * @property bool $is_demo
 * @property int $credit_fcfa
 * @property CarbonImmutable|null $suspended_at
 */
#[Fillable(['name', 'email', 'password', 'country_code'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasApiTokens<PersonalAccessToken> */
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Valeurs par défaut d'un nouveau compte (identiques à celles de la base).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Client->value,
        'is_demo' => false,
        'credit_fcfa' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_demo' => 'boolean',
            'credit_fcfa' => 'integer',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Profil public du développeur (créé en même temps que le compte).
     *
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Un compte suspendu par un administrateur ne peut plus agir.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
