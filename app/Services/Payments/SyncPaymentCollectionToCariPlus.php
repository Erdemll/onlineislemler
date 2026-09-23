<?php

namespace App\Services\Payments;

use App\Contracts\CariPlusGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\CariPlusException;
use App\Models\Payment;

class SyncPaymentCollectionToCariPlus
{
    public function __construct(private CariPlusGateway $cariPlus) {}

    public function handle(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Paid) {
            throw new CariPlusException('Ödemesi tamamlanmayan işlem Cari Plus tahsilatına aktarılamaz.');
        }

        if ($payment->cari_plus_collection_id !== null) {
            return;
        }

        $invoice = $payment->invoice;
        $accountId = (int) config('services.cari_plus.collection_account_id');

        if ($invoice?->cari_plus_invoice_id === null || $accountId <= 0) {
            throw new CariPlusException('Cari Plus tahsilatı için fatura ve banka hesabı gerekli.');
        }

        $amountKurus = $payment->amount_kurus;
        $result = $this->cariPlus->createInvoiceCollection(
            payload: [
                'sales_invoice_id' => $invoice->cari_plus_invoice_id,
                'type' => 'bank_transfer',
                'amount' => (float) ($amountKurus / 100),
                'collection_date' => ($payment->paid_at ?? $payment->created_at)->copy()->timezone('Europe/Istanbul')->toDateString(),
                'company_account_id' => $accountId,
                'description' => 'Akbank Aköde ile tahsilat - '.$payment->order_id,
            ],
            idempotencyKey: 'tahsilat-'.$payment->uuid,
        );

        $collectionId = $result['id'] ?? null;

        $collectedAmount = $result['amount'] ?? null;

        if (! is_int($collectionId) || $collectionId <= 0
            || ($result['sales_invoice_id'] ?? null) !== $invoice->cari_plus_invoice_id
            || ($result['type'] ?? null) !== 'bank_transfer'
            || ($result['currency'] ?? null) !== 'TRY'
            || ($result['company_account_id'] ?? null) !== $accountId
            || ! is_numeric($collectedAmount)
            || ! is_finite((float) $collectedAmount)
            || (int) round((float) $collectedAmount * 100) !== $amountKurus) {
            throw new CariPlusException('Cari Plus tahsilat kaydı beklenmeyen bir yanıt döndürdü.');
        }

        $payment->update([
            'cari_plus_collection_id' => $collectionId,
            'cari_plus_collection_synced_at' => now(),
        ]);
    }
}
