<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{route: string, view: string, heading: string}>
     */
    public static function pages(): array
    {
        return [
            'invoices' => [
                'route' => 'customer.invoices.index',
                'view' => 'customer.invoices.index',
                'heading' => 'Faturalar',
            ],
            'services' => [
                'route' => 'customer.services.index',
                'view' => 'customer.services.index',
                'heading' => 'Hizmetler ve Abonelikler',
            ],
            'support' => [
                'route' => 'customer.support.index',
                'view' => 'customer.support.index',
                'heading' => 'Destek',
            ],
            'contracts' => [
                'route' => 'customer.contracts.index',
                'view' => 'customer.contracts.index',
                'heading' => 'Sözleşmeler',
            ],
        ];
    }

    #[DataProvider('pages')]
    public function test_verified_customer_can_view_page(
        string $route,
        string $view,
        string $heading,
    ): void {
        $customer = Customer::factory()->phoneVerified()->create();

        $response = $this
            ->actingAsCustomer($customer)
            ->get(route($route));

        $response
            ->assertOk()
            ->assertViewIs($view)
            ->assertSeeText($heading);
    }

    #[DataProvider('pages')]
    public function test_guest_is_redirected_from_page(
        string $route,
        string $view,
        string $heading,
    ): void {
        $response = $this->get(route($route));

        $response->assertRedirect(route('customer.login'));
    }

    #[DataProvider('pages')]
    public function test_unverified_customer_is_redirected_from_page(
        string $route,
        string $view,
        string $heading,
    ): void {
        $customer = Customer::factory()->create();

        $response = $this
            ->actingAsCustomer($customer)
            ->get(route($route));

        $response->assertRedirect(route('customer.phone.verify'));
    }
}
