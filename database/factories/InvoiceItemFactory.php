<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'service_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'quantity' => 1,
            'unit_price' => 1200,
            'tax_rate' => 20,
            'line_total' => 1200,
        ];
    }
}
