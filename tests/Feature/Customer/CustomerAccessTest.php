<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get(
            route('customer.dashboard')
        );

        $response->assertRedirect(
            route('customer.login')
        );
    }

    public function test_unverified_customer_cannot_access_dashboard(): void
    {
        $customer = Customer::factory()
            ->create([
                'email_verified_at' => null,
            ]);

        $response = $this
            ->actingAsCustomer($customer)
            ->get(
                route('customer.dashboard')
            );

        $response->assertRedirect(
            route('customer.email.verify')
        );
    }

    public function test_verified_customer_can_access_dashboard(): void
    {
        $customer = Customer::factory()
            ->ready()
            ->create();

        $response = $this
            ->actingAsCustomer($customer)
            ->get(
                route('customer.dashboard')
            );

        $response->assertOk();
    }
}
