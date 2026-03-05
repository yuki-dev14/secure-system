<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     * Defaults to a Field Officer role — the most common user type.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'role'              => 'Field Officer',
            'office_location'   => fake()->randomElement([
                'DSWD Main Office',
                'Quezon City Field Office',
                'Manila Field Office',
                'Makati Field Office',
                'Caloocan Field Office',
                'Pasig Field Office',
            ]),
            'status'            => 'active',
            'last_login_at'     => fake()->optional(0.7)->dateTimeBetween('-30 days', 'now'),
            'remember_token'    => Str::random(10),
        ];
    }

    // ─── Named States ────────────────────────────────────────────────────────

    /**
     * Administrator role state.
     */
    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Administrator',
        ]);
    }

    /**
     * Field Officer role state.
     */
    public function fieldOfficer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Field Officer',
        ]);
    }

    /**
     * Compliance Verifier role state.
     */
    public function complianceVerifier(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Compliance Verifier',
        ]);
    }

    /**
     * Inactive user state.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => 'inactive',
            'last_login_at' => fake()->dateTimeBetween('-1 year', '-6 months'),
        ]);
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
}
