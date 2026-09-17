<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(12),
            'price' => fake()->randomFloat(2, 100, 5000),
            'currency' => 'TRY',
            'tax_rate' => 20,
            'price_includes_tax' => true,
            'cari_plus_service_id' => null,
            'cari_plus_product_id' => null,
            'cari_plus_sku' => null,
            'cari_plus_updated_at' => null,
            'synced_at' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
