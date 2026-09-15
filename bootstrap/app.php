<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\EnsureCustomerPhoneIsVerified;
use App\Http\Middleware\EnsureCustomerSessionIsCurrent;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'customer.phone.verified'
            => EnsureCustomerPhoneIsVerified::class,

            'customer.session.current'
            => EnsureCustomerSessionIsCurrent::class,
        ]);
        $middleware->redirectGuestsTo(
            fn(Request $request) => route('customer.login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
