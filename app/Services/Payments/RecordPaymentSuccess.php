<?php

namespace App\Services\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ServiceOrderStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class RecordPaymentSuccess
{
    public function handle(Payment $payment): bool
    {
        return DB::transaction(function () use ($payment): bool {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $invoice = $payment->invoice()->lockForUpdate()->first();

            if ($payment->status === PaymentStatus::Paid) {
                return $invoice?->status === InvoiceStatus::Paid;
            }

            if ($payment->status === PaymentStatus::Refunded || $invoice?->status !== InvoiceStatus::Unpaid) {
                return false;
            }

            $payment->status = PaymentStatus::Paid;
            $payment->paid_at = now();
            $payment->save();

            $invoice->update(['status' => InvoiceStatus::Paid]);

            if ($invoice->serviceOrder !== null
                && $invoice->serviceOrder->status !== ServiceOrderStatus::ServiceProvisioned) {
                $invoice->serviceOrder->update(['status' => ServiceOrderStatus::Paid]);
            }

            return true;
        });
    }
}
