<?php

namespace Database\Factories;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Models\Customer;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
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
            'ticket_number' => 'DST-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory()->ready(),
            'category' => SupportTicketCategory::Technical,
            'subject' => fake()->sentence(5),
            'status' => SupportTicketStatus::AwaitingSupport,
            'last_message_at' => now(),
            'closed_at' => null,
        ];
    }
}
