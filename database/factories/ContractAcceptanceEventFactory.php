<?php

namespace Database\Factories;

use App\Models\ContractAcceptanceEvent;
use App\Models\Customer;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractAcceptanceEvent>
 */
class ContractAcceptanceEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(),
            'contract_acceptance_id' => null,
            'customer_id' => Customer::factory()->ready(),
            'sequence' => 1,
            'event_type' => 'service_order_created',
            'metadata' => [],
            'previous_hash' => null,
            'event_hash' => str_repeat('a', 64),
            'hash_version' => 'sha256-v1',
            'occurred_at' => now(),
        ];
    }
}
