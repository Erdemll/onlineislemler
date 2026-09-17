<?php

namespace Database\Factories;

use App\Enums\ServiceOrderStatus;
use App\Models\ContractVersion;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
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
            'customer_id' => Customer::factory()->ready(),
            'service_id' => Service::factory(),
            'contract_version_id' => ContractVersion::factory(),
            'purchase_key' => hash('sha256', (string) Str::uuid()),
            'status' => ServiceOrderStatus::AwaitingContract,
            'service_name_snapshot' => fake()->words(3, true),
            'service_description_snapshot' => fake()->sentence(),
            'cari_plus_product_id_snapshot' => fake()->numberBetween(1000, 999999),
            'currency' => 'TRY',
            'unit_price' => 1499.90,
            'tax_rate' => 20,
            'price_includes_tax' => true,
            'last_error' => null,
        ];
    }
}
