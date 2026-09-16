<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'customer_id' => Customer::factory(),
            'service_id' => null,
            'cari_plus_invoice_id' => fake()->unique()->numberBetween(1000, 999999),
            'invoice_number' => 'FTR-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'status' => InvoiceStatus::Unpaid,
            'collection_status' => 'to_collect',
            'currency' => 'TRY',
            'subtotal' => 1000,
            'tax_amount' => 200,
            'total' => 1200,
            'invoice_date' => today(),
            'due_date' => today()->addDays(14),
            'idempotency_key' => 'factory-'.$uuid,
            'last_sync_error' => null,
            'synced_at' => now(),
        ];
    }
}
