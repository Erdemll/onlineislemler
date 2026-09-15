<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsCustomer(
        \App\Models\Customer $customer
    ): static {
        $this->actingAs(
            $customer,
            'customer'
        );

        $this->withSession([
            'customer_session_version'
            => $customer->session_version,
        ]);

        return $this;
    }
}
