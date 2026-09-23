<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController as AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\ContractAcceptanceController as AdminContractAcceptanceController;
use App\Http\Controllers\Admin\ContractController as AdminContractController;
use App\Http\Controllers\Admin\ContractDocumentController as AdminContractDocumentController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ProductSyncController as AdminProductSyncController;
use App\Http\Controllers\Admin\SupportReplyController as AdminSupportReplyController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Admin\SupportTicketStatusController as AdminSupportTicketStatusController;
use App\Http\Controllers\Customer\ContractAcceptanceController;
use App\Http\Controllers\Customer\ContractDocumentController;
use App\Http\Controllers\Customer\ContractSigningChallengeController;
use App\Http\Controllers\Customer\CurrentAccountProvisionController;
use App\Http\Controllers\Customer\EmailVerificationController;
use App\Http\Controllers\Customer\ForgotPasswordController;
use App\Http\Controllers\Customer\InvoiceController;
use App\Http\Controllers\Customer\InvoiceIssueController;
use App\Http\Controllers\Customer\InvoicePaymentController;
use App\Http\Controllers\Customer\InvoiceSyncController;
use App\Http\Controllers\Customer\LoginController;
use App\Http\Controllers\Customer\PhoneChangeController;
use App\Http\Controllers\Customer\PhoneVerificationController;
use App\Http\Controllers\Customer\ProductSyncController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\RegisterController;
use App\Http\Controllers\Customer\ServiceController;
use App\Http\Controllers\Customer\ServiceOrderContractController;
use App\Http\Controllers\Customer\ServicePurchaseController;
use App\Http\Controllers\Customer\SupportReplyController;
use App\Http\Controllers\Customer\SupportTicketController;
use App\Http\Controllers\Payments\ToslaCallbackController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('customer.dashboard');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/giris', [AdminAuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('/giris', [AdminAuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:admin-login')
        ->name('login.store');

    Route::middleware(['auth', 'can:access-admin'])->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::post('/cikis', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::get('/sozlesmeler', [AdminContractController::class, 'index'])->name('contracts.index');
        Route::get('/sozlesmeler/yeni', [AdminContractController::class, 'create'])->name('contracts.create');
        Route::post('/sozlesmeler', [AdminContractController::class, 'store'])->name('contracts.store');
        Route::get('/sozlesme-surumleri/{contractVersion:uuid}/indir', [AdminContractDocumentController::class, 'source'])
            ->name('contract-versions.download');

        Route::post('/urunler/esitle', AdminProductSyncController::class)
            ->middleware('throttle:admin-product-sync')
            ->name('products.sync');

        Route::get('/imzalanan-sozlesmeler', [AdminContractAcceptanceController::class, 'index'])
            ->name('contract-acceptances.index');
        Route::get('/imzalanan-sozlesmeler/{contractAcceptance:uuid}', [AdminContractAcceptanceController::class, 'show'])
            ->name('contract-acceptances.show');
        Route::get('/imzalanan-sozlesmeler/{contractAcceptance:uuid}/indir', [AdminContractDocumentController::class, 'signed'])
            ->name('contract-acceptances.download');

        Route::get('/destek', [AdminSupportTicketController::class, 'index'])->name('support.index');
        Route::get('/destek/{supportTicket:uuid}', [AdminSupportTicketController::class, 'show'])->name('support.show');
        Route::post('/destek/{supportTicket:uuid}/yanitlar', AdminSupportReplyController::class)
            ->middleware('throttle:admin-support')
            ->name('support.replies.store');
        Route::patch('/destek/{supportTicket:uuid}/durum', AdminSupportTicketStatusController::class)
            ->middleware('throttle:admin-support')
            ->name('support.status.update');
        // Customer management
        Route::get('/musteriler', [AdminCustomerController::class, 'index'])->name('customers.index');
        Route::get('/musteriler/{customer:uuid}/duzenle', [AdminCustomerController::class, 'edit'])->name('customers.edit');
        Route::patch('/musteriler/{customer:uuid}', [AdminCustomerController::class, 'update'])->name('customers.update');
    });
});

Route::middleware('guest:customer')->group(function () {

    Route::view('/giris', 'customer.auth.login')
        ->name('customer.login');

    Route::post('/giris', [LoginController::class, 'store'])
        ->middleware('throttle:customer-login')
        ->name('customer.login.store');

    Route::get('/kayit', [RegisterController::class, 'create'])
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
    'customer.email.verified',
])->group(function () {
    Route::get('/online-islemler/destek', [SupportTicketController::class, 'index'])
        ->name('customer.support.index');
    Route::get('/online-islemler/destek/yeni', [SupportTicketController::class, 'create'])
        ->name('customer.support.create');
    Route::post('/online-islemler/destek', [SupportTicketController::class, 'store'])
        ->middleware('throttle:customer-support')
        ->name('customer.support.store');
    Route::get('/online-islemler/destek/{supportTicket}', [SupportTicketController::class, 'show'])
        ->name('customer.support.show');
    Route::post('/online-islemler/destek/{supportTicket}/yanitlar', SupportReplyController::class)
        ->middleware('throttle:customer-support')
        ->name('customer.support.replies.store');
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
    'customer.email.verified',
    'customer.cari_plus.ready',
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

    Route::post(
        '/online-islemler/faturalar/{invoice:uuid}/ode',
        [InvoicePaymentController::class, 'store']
    )
        ->middleware('throttle:customer-payment')
        ->name('customer.invoices.pay');

    Route::get(
        '/online-islemler/hizmetler',
        [ServiceController::class, 'index']
    )->name('customer.services.index');

    Route::post(
        '/online-islemler/hizmetler/esitle',
        ProductSyncController::class
    )
        ->middleware('throttle:customer-product-sync')
        ->name('customer.services.sync');

    Route::post(
        '/online-islemler/hizmetler/{service}/talep-olustur',
        ServicePurchaseController::class
    )
        ->middleware('throttle:customer-purchase')
        ->name('customer.services.purchase');

    Route::get(
        '/online-islemler/sozlesmeler',
        [ContractAcceptanceController::class, 'index']
    )->name('customer.contracts.index');

    Route::get(
        '/online-islemler/hizmet-talepleri/{serviceOrder:uuid}/sozlesme',
        [ServiceOrderContractController::class, 'show']
    )->name('customer.service-orders.contract.show');

    Route::get(
        '/online-islemler/hizmet-talepleri/{serviceOrder:uuid}/sozlesme-belgesi',
        [ContractDocumentController::class, 'source']
    )->name('customer.service-orders.contract.document');

    Route::post(
        '/online-islemler/hizmet-talepleri/{serviceOrder:uuid}/sozlesme-kodu',
        ContractSigningChallengeController::class
    )
        ->middleware('throttle:customer-contract-code')
        ->name('customer.service-orders.contract.challenge');

    Route::post(
        '/online-islemler/hizmet-talepleri/{serviceOrder:uuid}/sozlesme-kabul',
        [ContractAcceptanceController::class, 'store']
    )
        ->middleware('throttle:customer-contract-verify')
        ->name('customer.service-orders.contract.accept');

    Route::get(
        '/online-islemler/sozlesmeler/{contractAcceptance:uuid}/indir',
        [ContractDocumentController::class, 'signed']
    )->name('customer.contracts.download');

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
    'customer.email.verified',
    'customer.cari_plus.ready',
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

    Route::post(
        '/e-posta-dogrula/cari-hesap',
        CurrentAccountProvisionController::class
    )
        ->middleware([
            'customer.email.verified',
            'throttle:customer-current-account-provision',
        ])
        ->name('customer.current-account.provision');
});

Route::post('/odeme/callback/akode', ToslaCallbackController::class)
    ->middleware('throttle:payment-callback')
    ->name('payment.callback.akode');
