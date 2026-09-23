<?php

namespace App\Console\Commands;

use App\Contracts\ToslaGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\CariPlusException;
use App\Exceptions\ToslaException;
use App\Models\Payment;
use App\Services\Payments\CompleteToslaPayment;
use App\Services\Payments\SyncPaymentCollectionToCariPlus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:reconcile {--older-than=30 : Bekleyen işlemler için dakika cinsinden yaş eşiği} {--order= : Tek bir siparişi yeniden sorgula}')]
#[Description('Aköde tarafında bekleyen ödemeleri sorgular ve Cari Plus tahsilat senkronunu tamamlar')]
class ReconcilePayments extends Command
{
    public function handle(
        ToslaGateway $tosla,
        CompleteToslaPayment $completePayment,
        SyncPaymentCollectionToCariPlus $syncCollection,
    ): int {
        $threshold = now()->subMinutes(max(1, (int) $this->option('older-than')));
        $orderId = $this->option('order');

        if (is_string($orderId) && $orderId !== '') {
            $payments = Payment::query()->where('order_id', $orderId)->limit(1)->get();

            if ($payments->isEmpty()) {
                $this->error('Ödeme kaydı bulunamadı.');

                return self::FAILURE;
            }
        } else {
            $payments = Payment::query()
                ->where('created_at', '<=', $threshold)
                ->where(function ($query): void {
                    $query->where('status', PaymentStatus::Pending)
                        ->orWhere(function ($query): void {
                            $query->whereIn('status', [PaymentStatus::Failed, PaymentStatus::Cancelled])
                                ->where('created_at', '>=', now()->subHours(48));
                        });
                })
                ->orderBy('updated_at')
                ->orderBy('id')
                ->limit(50)
                ->get();
        }

        foreach ($payments as $payment) {
            if ($payment->status === PaymentStatus::Paid || $payment->status === PaymentStatus::Refunded) {
                continue;
            }

            try {
                $inquiry = $tosla->inquiry($payment->order_id);
            } catch (ToslaException $exception) {
                report($exception);
                $payment->touch();

                continue;
            }

            match ($inquiry['request_status']) {
                1 => $completePayment->handle($payment, $inquiry) ?: $payment->touch(),
                0 => $payment->update(['status' => PaymentStatus::Failed, 'response_code' => $inquiry['bank_response_code']]),
                2, 4 => $payment->update(['status' => PaymentStatus::Cancelled, 'response_code' => $inquiry['bank_response_code']]),
                default => $payment->touch(),
            };
        }

        $this->syncRemainingCollections($syncCollection, is_string($orderId) && $orderId !== '' ? $orderId : null);

        return self::SUCCESS;
    }

    private function syncRemainingCollections(SyncPaymentCollectionToCariPlus $syncCollection, ?string $orderId): void
    {
        $payments = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereNull('cari_plus_collection_id')
            ->when($orderId !== null, fn ($query) => $query->where('order_id', $orderId))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($payments as $payment) {
            try {
                $syncCollection->handle($payment);
            } catch (CariPlusException $exception) {
                report($exception);
                $payment->touch();
            }
        }
    }
}
