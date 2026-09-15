<?php

use App\Http\Controllers\Customer\RegisterController;
use App\Http\Controllers\Customer\LoginController;
use App\Http\Controllers\Customer\PhoneVerificationController;
use App\Http\Controllers\Customer\ForgotPasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:customer')->group(function () {

    Route::view('/giris', 'customer.auth.login')
        ->name('customer.login');

    Route::post('/giris', [LoginController::class, 'store'])
        ->middleware('throttle:customer-login')
        ->name('customer.login.store');

    Route::view('/kayit', 'customer.auth.register')
        ->name('customer.register');

    Route::post('/kayit', [RegisterController::class, 'store'])
        ->middleware('throttle:customer-register')
        ->name('customer.register.store');

    Route::get(
        '/kayit/telefon-dogrulama',
        function () {
            return view('customer.phone-verify');
        }
    )
        ->name('customer.phone-verify');

    Route::get(
        '/sifremi-unuttum',
        [
            ForgotPasswordController::class,
            'showRequestForm',
        ]
    )->name(
        'customer.password.request'
    );

    Route::post(
        '/sifremi-unuttum',
        [
            ForgotPasswordController::class,
            'sendOtp',
        ]
    )
        ->middleware(
            'throttle:customer-password-forgot'
        )
        ->name(
            'customer.password.send'
        );

    Route::get(
        '/sifre-dogrulama',
        [
            ForgotPasswordController::class,
            'showOtpForm',
        ]
    )->name(
        'customer.password.otp.form'
    );

    Route::post(
        '/sifre-dogrulama',
        [
            ForgotPasswordController::class,
            'verifyOtp',
        ]
    )
        ->middleware(
            'throttle:customer-password-otp'
        )
        ->name(
            'customer.password.otp.verify'
        );

    Route::get(
        '/sifre-sifirla',
        [
            ForgotPasswordController::class,
            'showResetForm',
        ]
    )->name(
        'customer.password.reset'
    );

    Route::post(
        '/sifre-sifirla',
        [
            ForgotPasswordController::class,
            'reset',
        ]
    )->name(
        'customer.password.update'
    );
});

Route::middleware([
    'auth:customer',
    'customer.session.current',
])->group(function () {

    Route::get(
        '/telefon-dogrula',
        [
            PhoneVerificationController::class,
            'show',
        ]
    )->name('customer.phone.verify');

    Route::post(
        '/telefon-dogrula',
        [
            PhoneVerificationController::class,
            'verify',
        ]
    )
        ->middleware('throttle:customer-otp-verify')
        ->name('customer.phone.verify.store');

    Route::post(
        '/telefon-dogrula/tekrar-gonder',
        [
            PhoneVerificationController::class,
            'resend',
        ]
    )
        ->middleware('throttle:customer-otp-resend')
        ->name('customer.phone.resend');

    Route::post(
        '/cikis',
        [
            LoginController::class,
            'destroy',
        ]
    )->name('customer.logout');
});

Route::middleware([
    'auth:customer',
    'customer.session.current',
    'customer.phone.verified',
])->group(function () {

    Route::view(
        '/online-islemler',
        'customer.dashboard'
    )->name('customer.dashboard');
});
