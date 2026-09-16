<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_session_version_allows_access(): void
    {
        $customer = Customer::factory()
            ->phoneVerified()
            ->create([
                'session_version' => 3,
            ]);

        $response = $this
            ->actingAs(
                $customer,
                'customer'
            )
            ->withSession([
                'customer_session_version' => 3,
            ])
            ->get(
                route('customer.dashboard')
            );

        $response->assertOk();
    }

    public function test_old_session_version_forces_logout(): void
    {
        $customer = Customer::factory()
            ->phoneVerified()
            ->create([
                'session_version' => 5,
            ]);

        $response = $this
            ->actingAs(
                $customer,
                'customer'
            )
            ->withSession([
                'customer_session_version' => 4,
            ])
            ->get(
                route('customer.dashboard')
            );

        $this->assertGuest(
            'customer'
        );

        $response->assertRedirect(
            route('customer.login')
        );
    }

    public function test_missing_session_version_forces_logout(): void
    {
        $customer = Customer::factory()
            ->phoneVerified()
            ->create([
                'session_version' => 2,
            ]);

        $response = $this
            ->actingAs(
                $customer,
                'customer'
            )
            ->get(
                route('customer.dashboard')
            );

        $this->assertGuest(
            'customer'
        );

        $response->assertRedirect(
            route('customer.login')
        );
    }
}
