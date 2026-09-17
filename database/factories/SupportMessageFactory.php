<?php

namespace Database\Factories;

use App\Enums\SupportMessageSender;
use App\Models\Customer;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportMessage>
 */
class SupportMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'sender_type' => SupportMessageSender::Customer,
            'customer_id' => Customer::factory()->ready(),
            'user_id' => null,
            'sender_name_snapshot' => fake()->name(),
            'body' => fake()->paragraph(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
        ];
    }
}
