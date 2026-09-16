<?php

use App\Http\Controllers\Customer\EmailVerificationController;
use App\Http\Controllers\Customer\ForgotPasswordController;
use App\Http\Controllers\Customer\InvoiceController;
use App\Http\Controllers\Customer\InvoiceIssueController;
use App\Http\Controllers\Customer\InvoiceSyncController;
use App\Http\Controllers\Customer\LoginController;
use App\Http\Controllers\Customer\PhoneChangeController;
use App\Http\Controllers\Customer\PhoneVerificationController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\RegisterController;
use App\Http\Controllers\Customer\ServiceController;
use App\Http\Controllers\Customer\ServicePurchaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('customer.dashboard');
});

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
        ->middleware('throttle:customer-verification-verify')
        ->name('customer.phone.verify.store');

    Route::post(
        '/telefon-dogrula/tekrar-gonder',
        [
            PhoneVerificationController::class,
            'resend',
        ]
    )
        ->middleware('throttle:customer-verification-resend')
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

    Route::get(
        '/online-islemler/faturalar',
        [InvoiceController::class, 'index']
    )->name('customer.invoices.index');

    Route::post(
        '/online-islemler/faturalar/esitle',
        InvoiceSyncController::class
    )
        ->middleware('throttle:customer-invoice-sync')
        ->name('customer.invoices.sync');

    Route::post(
        '/online-islemler/faturalar/{invoice:uuid}/tekrar-dene',
        InvoiceIssueController::class
    )
        ->middleware('throttle:customer-purchase')
        ->name('customer.invoices.retry');

    Route::get(
        '/online-islemler/hizmetler',
        [ServiceController::class, 'index']
    )->name('customer.services.index');

    Route::post(
        '/online-islemler/hizmetler/{service}/fatura-olustur',
        ServicePurchaseController::class
    )
        ->middleware('throttle:customer-purchase')
        ->name('customer.services.purchase');

    Route::view(
        '/online-islemler/destek',
        'customer.support.index'
    )->name('customer.support.index');

    Route::view(
        '/online-islemler/sozlesmeler',
        'customer.contracts.index'
    )->name('customer.contracts.index');

    Route::get(
        '/online-islemler/bilgilerim',
        [ProfileController::class, 'show']
    )->name('customer.profile');

    Route::patch(
        '/online-islemler/bilgilerim',
        [ProfileController::class, 'update']
    )->name('customer.profile.update');
});

Route::middleware([
    'auth:customer',
    'customer.session.current',
    'customer.phone.verified',
])->group(function () {

    Route::post(
        '/online-islemler/bilgilerim/telefon-degistir',
        [PhoneChangeController::class, 'request']
    )
        ->middleware('throttle:customer-verification-resend')
        ->name(
            'customer.phone.change.request'
        );

    Route::get(
        '/online-islemler/bilgilerim/telefon-dogrula',
        [PhoneChangeController::class, 'showVerifyForm']
    )->name(
        'customer.phone.change.verify.form'
    );

    Route::post(
        '/online-islemler/bilgilerim/telefon-dogrula',
        [PhoneChangeController::class, 'verify']
    )
        ->middleware(
            'throttle:customer-verification-verify'
        )
        ->name(
            'customer.phone.change.verify'
        );
});

Route::middleware([
    'auth:customer',
    'customer.session.current',
])->group(function () {

    Route::get(
        '/e-posta-dogrula',
        [
            EmailVerificationController::class,
            'show',
        ]
    )->name(
        'customer.email.verify'
    );

    Route::post(
        '/e-posta-dogrula',
        [
            EmailVerificationController::class,
            'verify',
        ]
    )
        ->middleware(
            'throttle:customer-verification-verify'
        )
        ->name(
            'customer.email.verify.store'
        );

    Route::post(
        '/e-posta-dogrula/tekrar-gonder',
        [
            EmailVerificationController::class,
            'resend',
        ]
    )
        ->middleware(
            'throttle:customer-verification-resend'
        )
        ->name(
            'customer.email.resend'
        );
});
