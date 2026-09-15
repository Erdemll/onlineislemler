<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Contracts\SmsSender;
use App\Services\Sms\NetgsmSmsService;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SmsSender::class,
            NetgsmSmsService::class
        );
    }

    public function boot(): void
    {
        Password::defaults(function () {

            $rule = Password::min(12)
                ->max(128);

            if ($this->app->isProduction()) {
                $rule->uncompromised();
            }

            return $rule;
        });

        RateLimiter::for(
            'customer-register',
            function (Request $request) {

                return [
                    Limit::perMinute(5)
                        ->by($request->ip()),

                    Limit::perHour(10)
                        ->by($request->ip()),
                ];
            }
        );

        RateLimiter::for('customer-login', function (Request $request) {
            $email = mb_strtolower(
                trim((string) $request->input('email'))
            );

            return [
                Limit::perMinute(5)
                    ->by($email . '|' . $request->ip()),

                Limit::perMinute(20)
                    ->by($request->ip()),
            ];
        });

        RateLimiter::for(
            'customer-otp-verify',
            function (Request $request) {

                $customer = $request->user('customer');

                return [
                    Limit::perMinute(10)
                        ->by(
                            $customer?->id
                                . '|' .
                                $request->ip()
                        ),
                ];
            }
        );

        RateLimiter::for(
            'customer-otp-resend',
            function (Request $request) {

                $customer = $request->user('customer');

                return [
                    Limit::perMinute(1)
                        ->by($customer->id),

                    Limit::perHour(5)
                        ->by($customer->id),
                ];
            }
        );

        RateLimiter::for(
            'customer-password-forgot',
            function (Request $request) {

                return [
                    Limit::perMinute(3)
                        ->by($request->ip()),

                    Limit::perHour(10)
                        ->by($request->ip()),
                ];
            }
        );

        RateLimiter::for(
            'customer-password-otp',
            function (Request $request) {

                return [
                    Limit::perMinute(10)
                        ->by($request->ip()),
                ];
            }
        );
    }
}
