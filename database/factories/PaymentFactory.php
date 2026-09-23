<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'customer_id' => Customer::factory(),
            'invoice_id' => Invoice::factory(),
            'order_id' => 'OI'.strtoupper(Str::random(18)),
            'three_d_session_id' => null,
            'transaction_id' => null,
            'amount_kurus' => fake()->numberBetween(1000, 500000),
            'currency' => 'TRY',
            'status' => PaymentStatus::Pending,
            'installment_count' => 0,
            'response_code' => null,
            'paid_at' => null,
            'cari_plus_collection_id' => null,
            'cari_plus_collection_synced_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
