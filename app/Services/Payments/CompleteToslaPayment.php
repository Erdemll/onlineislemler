<?php

namespace App\Services\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\CariPlusException;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class CompleteToslaPayment
{
    public function __construct(
        private RecordPaymentSuccess $recordSuccess,
        private SyncPaymentCollectionToCariPlus $syncCollection,
    ) {}

    /** @param array{order_id: string, transaction_id: string, request_status: int, bank_response_code: string, amount: int, currency: int} $inquiry */
    public function handle(Payment $payment, array $inquiry): bool
    {
        if ($inquiry['request_status'] !== 1
            || $inquiry['bank_response_code'] !== '00'
            || $inquiry['order_id'] !== $payment->order_id
            || $inquiry['amount'] !== $payment->amount_kurus
            || $inquiry['currency'] !== 949
            || $payment->currency !== 'TRY'
            || $payment->status === PaymentStatus::Refunded
            || ! in_array($payment->invoice?->status, [InvoiceStatus::Unpaid, InvoiceStatus::Paid], true)) {
            Log::warning('Aköde sorgu ödeme bilgileriyle eşleşmedi.', [
                'order_id' => $payment->order_id,
                'inquiry_order_id' => $inquiry['order_id'],
                'payment_amount' => $payment->amount_kurus,
                'inquiry_amount' => $inquiry['amount'],
                'inquiry_currency' => $inquiry['currency'],
                'inquiry_request_status' => $inquiry['request_status'],
                'inquiry_bank_response_code' => $inquiry['bank_response_code'],
            ]);

            return false;
        }

        if (! $this->recordSuccess->handle($payment)) {
            Log::warning('Aköde odemesi yerel fatura durumuyla celisiyor.', [
                'order_id' => $payment->order_id,
                'invoice_id' => $payment->invoice_id,
            ]);

            return false;
        }

        try {
            $this->syncCollection->handle($payment->fresh());
        } catch (CariPlusException $exception) {
            report($exception);
        }

        return true;
    }
}
