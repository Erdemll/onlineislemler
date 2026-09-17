<?php

namespace Database\Factories;

use App\Models\ContractSigningChallenge;
use App\Models\Customer;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContractSigningChallenge>
 */
class ContractSigningChallengeFactory extends Factory
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
            'service_order_id' => ServiceOrder::factory(),
            'customer_id' => Customer::factory()->ready(),
            'delivery_channel' => 'email',
            'delivery_destination' => fake()->safeEmail(),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'signature_path' => 'contracts/signatures/test.enc',
            'signature_hash' => str_repeat('a', 64),
            'source_document_hash' => str_repeat('b', 64),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'session_identifier_hash' => str_repeat('c', 64),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
            'consumed_at' => null,
        ];
    }
}
