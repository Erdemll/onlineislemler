<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_login(): void
    {
        $customer = Customer::factory()
            ->phoneVerified()
            ->create([
                'email' => 'erdem@example.com',
            ]);

        $response = $this->post(
            route('customer.login.store'),
            [
                'email' => 'erdem@example.com',
                'password' => 'TestPassword123!',
            ]
        );

        $this->assertAuthenticatedAs(
            $customer,
            'customer'
        );

        $response->assertRedirect(
            route('customer.dashboard')
        );
    }


    public function test_customer_cannot_login_with_wrong_password(): void
    {
        Customer::factory()->create([
            'email' => 'erdem@example.com',
        ]);

        $response = $this->post(
            route('customer.login.store'),
            [
                'email' => 'erdem@example.com',
                'password' => 'wrong-password',
            ]
        );

        $this->assertGuest('customer');

        $response->assertSessionHasErrors(
            'email'
        );
    }


    public function test_inactive_customer_cannot_login(): void
    {
        Customer::factory()
            ->inactive()
            ->create([
                'email' => 'erdem@example.com',
            ]);

        $this->post(
            route('customer.login.store'),
            [
                'email' => 'erdem@example.com',
                'password' => 'TestPassword123!',
            ]
        );

        $this->assertGuest('customer');
    }


    public function test_unverified_customer_is_redirected_to_phone_verification(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'erdem@example.com',
        ]);

        $response = $this->post(
            route('customer.login.store'),
            [
                'email' => 'erdem@example.com',
                'password' => 'TestPassword123!',
            ]
        );

        $this->assertAuthenticatedAs(
            $customer,
            'customer'
        );

        $response->assertRedirect(
            route('customer.phone.verify')
        );
    }

    public function test_customer_can_logout(): void
    {
        $customer = Customer::factory()
            ->phoneVerified()
            ->create();

        $response = $this
            ->actingAs($customer, 'customer')
            ->post(
                route('customer.logout')
            );

        $this->assertGuest('customer');

        $response->assertRedirect(
            route('customer.login')
        );
    }
}
