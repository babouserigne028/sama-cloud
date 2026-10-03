<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Application\Community\Actions\CreateProfile;
use App\Domain\Account\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Client,
            'country_code' => 'SN',
            'is_demo' => false,
            'credit_fcfa' => 0,
            'suspended_at' => null,
        ];
    }

    /**
     * Comme à l'inscription réelle, chaque compte fabriqué reçoit son profil public.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            app(CreateProfile::class)->handle($user);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Administrateur Systalink.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Compte de démonstration du jury.
     */
    public function demo(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_demo' => true,
        ]);
    }

    /**
     * Compte suspendu par un administrateur.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'suspended_at' => now(),
        ]);
    }
}
