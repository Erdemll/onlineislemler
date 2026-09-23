<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentCallback;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentCallback>
 */
class PaymentCallbackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'payment_id' => Payment::factory(),
            'order_id' => 'OI'.strtoupper(Str::random(18)),
            'transaction_id' => null,
            'code' => '0',
            'message' => 'Başarılı',
            'bank_response_code' => null,
            'bank_response_message' => null,
            'request_status' => null,
            'md_status' => null,
            'hash_valid' => true,
            'received_at' => now(),
        ];
    }
}
