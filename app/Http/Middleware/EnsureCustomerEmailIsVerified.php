<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = auth(
            'customer'
        )->user();

        if (! $customer) {
            return redirect()
                ->route(
                    'customer.login'
                );
        }

        if (! $customer->email_verified_at) {
            return redirect()
                ->route(
                    'customer.email.verify'
                );
        }

        return $next($request);
    }
}
