<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerSessionIsCurrent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard(
            'customer'
        )->user();

        if (! $customer) {
            return $next($request);
        }

        $sessionVersion = $request
            ->session()
            ->get(
                'customer_session_version'
            );

        if (
            ! $customer->is_active
            || $sessionVersion === null
            || (int) $sessionVersion
            !== (int) $customer->session_version
        ) {
            Auth::guard(
                'customer'
            )->logout();

            $request->session()
                ->invalidate();

            $request->session()
                ->regenerateToken();

            return redirect()
                ->route(
                    'customer.login'
                )
                ->with(
                    'status',
                    'Güvenlik nedeniyle tekrar giriş yapmanız gerekiyor.'
                );
        }

        return $next($request);
    }
}
