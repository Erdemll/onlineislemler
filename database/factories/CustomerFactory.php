<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),

            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),

            'email' => fake()->unique()->safeEmail(),

            'phone' => '+905'
                . fake()->unique()->numerify('#########'),

            'password' => Hash::make('TestPassword123!'),

            'email_verified_at' => null,
            'phone_verified_at' => null,

            'is_active' => true,

            'session_version' => 1,
            'password_changed_at' => null,

            'remember_token' => null,
        ];
    }

    public function phoneVerified(): static
    {
        return $this->state(fn() => [
            'phone_verified_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn() => [
            'is_active' => false,
        ]);
    }
}
