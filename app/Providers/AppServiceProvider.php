<?php

namespace App\Providers;

use App\Contracts\CariPlusGateway;
use App\Contracts\SmsSender;
use App\Contracts\VerificationCodeSender;
use App\Models\User;
use App\Services\CariPlus\CariPlusClient;
use App\Services\Sms\VerimorSmsService;
use App\Services\Verification\EmailVerificationCodeSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            VerificationCodeSender::class,
            EmailVerificationCodeSender::class
        );

        $this->app->bind(
            SmsSender::class,
            VerimorSmsService::class
        );

        $this->app->bind(
            CariPlusGateway::class,
            CariPlusClient::class
        );
    }

    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user): bool => (bool) $user->is_admin);

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
                    ->by($email.'|'.$request->ip()),

                Limit::perMinute(20)
                    ->by($request->ip()),
            ];
        });

        RateLimiter::for('admin-login', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('admin-product-sync', function (Request $request) {
            return Limit::perMinute(1)->by($request->user()?->id.'|admin-products');
        });

        RateLimiter::for(
            'customer-verification-verify',
            function (Request $request) {

                $customer = $request->user('customer');

                return [
                    Limit::perMinute(10)
                        ->by(
                            $customer?->id
                                .'|'.
                                $request->ip()
                        ),
                ];
            }
        );

        RateLimiter::for(
            'customer-verification-resend',
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

        RateLimiter::for('customer-current-account-provision', function (Request $request) {
            return Limit::perMinute(2)->by($request->user('customer')->id);
        });

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

        RateLimiter::for('customer-purchase', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->user('customer')->id);
        });

        RateLimiter::for('customer-invoice-sync', function (Request $request) {
            return Limit::perMinute(2)
                ->by($request->user('customer')->id);
        });

        RateLimiter::for('customer-product-sync', function () {
            return Limit::perMinute(1)
                ->by('cari-plus-products');
        });

        RateLimiter::for('customer-contract-code', function (Request $request) {
            return [
                Limit::perMinute(1)->by($request->user('customer')->id),
                Limit::perHour(5)->by($request->user('customer')->id),
            ];
        });

        RateLimiter::for('customer-contract-verify', function (Request $request) {
            return Limit::perMinute(10)->by($request->user('customer')->id.'|'.$request->ip());
        });
    }
}
