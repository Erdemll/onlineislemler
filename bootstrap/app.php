<?php

use App\Http\Middleware\EnsureCustomerCariPlusAccountIsReady;
use App\Http\Middleware\EnsureCustomerEmailIsVerified;
use App\Http\Middleware\EnsureCustomerPhoneIsVerified;
use App\Http\Middleware\EnsureCustomerSessionIsCurrent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'customer.cari_plus.ready' => EnsureCustomerCariPlusAccountIsReady::class,
            'customer.email.verified' => EnsureCustomerEmailIsVerified::class,
            'customer.phone.verified' => EnsureCustomerPhoneIsVerified::class,

            'customer.session.current' => EnsureCustomerSessionIsCurrent::class,
        ]);
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin', 'admin/*')
                ? route('admin.login')
                : route('customer.login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
