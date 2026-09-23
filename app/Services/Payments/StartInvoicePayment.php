<?php

namespace App\Services\Payments;

use App\Contracts\ToslaGateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ToslaException;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StartInvoicePayment
{
    public function __construct(private ToslaGateway $tosla) {}

    /**
     * @return array{payment: Payment, redirect_url: string}
     */
    public function handle(Invoice $invoice): array
    {
        try {
            return Cache::lock('akode.invoice-start.'.$invoice->id, 120)
                ->block(1, fn (): array => $this->startLocked($invoice));
        } catch (LockTimeoutException $exception) {
            throw new ToslaException('Bu fatura için ödeme işlemi başlatılıyor. Lütfen kısa süre sonra tekrar deneyin.', previous: $exception);
        }
    }

    /** @return array{payment: Payment, redirect_url: string} */
    private function startLocked(Invoice $invoice): array
    {
        $invoice->refresh();

        if ($invoice->status !== InvoiceStatus::Unpaid) {
            throw new ToslaException('Bu fatura ödeme kabul etmiyor.');
        }

        $hasPending = $invoice->payments()
            ->where('status', PaymentStatus::Pending)
            ->exists();

        if ($hasPending) {
            throw new ToslaException('Bu fatura için bekleyen bir ödeme işlemi bulunuyor. Lütfen kısa süre sonra tekrar deneyin.');
        }

        $orderId = $this->uniqueOrderId();
        $amountKurus = (int) round((float) $invoice->total * 100);

        if ($amountKurus <= 0 || $invoice->currency !== 'TRY' || $invoice->cari_plus_invoice_id === null) {
            throw new ToslaException('Bu fatura şu anda kart ile ödemeye uygun değil.');
        }

        $configuredCallbackUrl = config('services.akode.callback_url');

        if (app()->isProduction() && blank($configuredCallbackUrl)) {
            throw new ToslaException('Ödeme dönüş adresi yapılandırılmadı.');
        }

        $callbackUrl = (string) ($configuredCallbackUrl ?: route('payment.callback.akode'));

        if (filter_var($callbackUrl, FILTER_VALIDATE_URL) === false
            || (app()->isProduction() && (parse_url($callbackUrl, PHP_URL_SCHEME) !== 'https'
                || in_array(parse_url($callbackUrl, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true)))) {
            throw new ToslaException('Ödeme dönüş adresi geçerli bir HTTPS adresi olmalıdır.');
        }

        $result = $this->tosla->startThreeDPayment(
            orderId: $orderId,
            amountKurus: $amountKurus,
            callbackUrl: $callbackUrl,
            description: 'Tepenet Güvenlik - '.($invoice->invoice_number ?? 'fatura ödemesi'),
            echo: $invoice->uuid,
        );

        $payment = Payment::create([
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'order_id' => $orderId,
            'three_d_session_id' => $result['three_d_session_id'],
            'transaction_id' => $result['transaction_id'],
            'amount_kurus' => $amountKurus,
            'currency' => 'TRY',
            'status' => PaymentStatus::Pending,
        ]);

        return [
            'payment' => $payment,
            'redirect_url' => $this->tosla->sharedPaymentUrl($result['three_d_session_id']),
        ];
    }

    private function uniqueOrderId(): string
    {
        do {
            $orderId = 'OI'.strtoupper(Str::random(18));
        } while (Payment::query()->where('order_id', $orderId)->exists());

        return $orderId;
    }
}
