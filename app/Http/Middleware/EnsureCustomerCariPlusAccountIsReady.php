<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerCariPlusAccountIsReady
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('customer')?->cari_plus_current_account_id === null) {
            return redirect()->route('customer.email.verify');
        }

        return $next($request);
    }
}
