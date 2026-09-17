<?php

namespace Database\Factories;

use App\Enums\CustomerType;
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
            'cari_plus_current_account_id' => null,
            'cari_plus_current_account_code' => null,
            'account_type' => CustomerType::Individual,
            'national_id' => $this->validNationalId(),
            'tax_number' => null,
            'tax_office' => null,
            'company_title' => null,

            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),

            'email' => fake()->unique()->safeEmail(),

            'phone' => '+905'
                .fake()->unique()->numerify('#########'),
            'mobile_phone' => null,
            'province_code' => '33',
            'province' => 'Mersin',
            'district' => 'Yenişehir',
            'address_line' => fake()->streetAddress(),
            'is_public_institution' => false,
            'spending_unit_tax_number' => null,
            'spending_unit_title' => null,

            'password' => Hash::make('TestPassword123!'),

            'email_verified_at' => null,
            'phone_verified_at' => null,

            'is_active' => true,

            'session_version' => 1,
            'password_changed_at' => null,

            'remember_token' => null,
        ];
    }

    public function emailVerified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => now(),
        ]);
    }

    public function phoneVerified(): static
    {
        return $this->state(fn () => [
            'phone_verified_at' => now(),
        ]);
    }

    public function ready(): static
    {
        return $this->emailVerified()->state(fn () => [
            'cari_plus_current_account_id' => fake()->unique()->numberBetween(1000, 999999),
            'cari_plus_current_account_code' => 'MUS'.fake()->unique()->numerify('######'),
        ]);
    }

    public function corporate(): static
    {
        return $this->state(fn () => [
            'account_type' => CustomerType::Corporate,
            'national_id' => null,
            'tax_number' => fake()->unique()->numerify('##########'),
            'tax_office' => 'Mersin Vergi Dairesi',
            'company_title' => fake()->company(),
            'mobile_phone' => '+905'.fake()->unique()->numerify('#########'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    private function validNationalId(): string
    {
        $digits = array_map('intval', str_split((string) fake()->unique()->numberBetween(100000000, 999999999)));
        $oddTotal = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $evenTotal = $digits[1] + $digits[3] + $digits[5] + $digits[7];
        $digits[] = (($oddTotal * 7) - $evenTotal) % 10;
        $digits[] = array_sum($digits) % 10;

        return implode('', $digits);
    }
}
