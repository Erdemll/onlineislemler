<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateVerifiedTestCustomerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_verified_test_customer(): void
    {
        $this->artisan('customer:create-test', [
            'email' => 'demo@example.com',
            'phone' => '0555 123 45 67',
            '--password' => 'TestPassword123!',
        ])
            ->expectsOutputToContain('Doğrulanmış test müşterisi hazır.')
            ->assertSuccessful();

        $customer = Customer::where('email', 'demo@example.com')->firstOrFail();

        $this->assertSame('+905551234567', $customer->phone);
        $this->assertTrue($customer->is_active);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertTrue(Hash::check('TestPassword123!', $customer->password));
    }

    public function test_it_updates_the_same_test_customer_instead_of_creating_a_duplicate(): void
    {
        Customer::factory()->create([
            'email' => 'demo@example.com',
            'phone' => '+905551234567',
            'phone_verified_at' => null,
            'is_active' => false,
        ]);

        $this->artisan('customer:create-test', [
            'email' => 'demo@example.com',
            'phone' => '05551234567',
            '--password' => 'NewTestPassword123!',
        ])->assertSuccessful();

        $customer = Customer::sole();

        $this->assertDatabaseCount('customers', 1);
        $this->assertTrue($customer->is_active);
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertTrue(Hash::check('NewTestPassword123!', $customer->password));
    }

    public function test_it_rejects_an_invalid_phone_number(): void
    {
        $this->artisan('customer:create-test', [
            'email' => 'demo@example.com',
            'phone' => '02121234567',
            '--password' => 'TestPassword123!',
        ])->assertFailed();

        $this->assertDatabaseCount('customers', 0);
    }
}
