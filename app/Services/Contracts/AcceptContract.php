<?php

namespace App\Services\Contracts;

use App\Enums\ContractAcceptanceMethod;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceOrderStatus;
use App\Exceptions\CariPlusException;
use App\Exceptions\ContractSigningException;
use App\Models\ContractAcceptance;
use App\Models\ContractSigningChallenge;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Services\Billing\CreateServiceInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class AcceptContract
{
    public function __construct(
        private GenerateSignedContractPdf $pdf,
        private RecordContractAuditEvent $audit,
        private CreateServiceInvoice $createInvoice,
        private EncryptedContractDocumentStorage $documents,
    ) {}

    public function accept(
        Customer $customer,
        ServiceOrder $order,
        string $challengeUuid,
        string $code,
        string $ipAddress,
        ?string $userAgent,
        string $sessionIdentifier,
    ): ContractAcceptance {
        $order->loadMissing('contractVersion.contract');

        if ($order->customer_id !== $customer->id) {
            throw new ContractSigningException('Bu hizmet talebine erişemezsiniz.');
        }

        $existing = $order->acceptance;

        if ($existing !== null) {
            return $existing;
        }

        $challenge = ContractSigningChallenge::query()
            ->where('uuid', $challengeUuid)
            ->whereBelongsTo($order)
            ->whereBelongsTo($customer)
            ->first();

        if ($challenge === null) {
            throw new ContractSigningException('Sözleşme doğrulama kaydı bulunamadı.');
        }

        $sessionIdentifierHash = hash('sha256', $sessionIdentifier);
        $this->verifyChallenge($challenge, $code, $sessionIdentifierHash);

        $acceptanceUuid = (string) Str::uuid();
        $acceptedAt = now();
        $generated = $this->pdf->generate($order, $customer, $challenge, $acceptanceUuid, $acceptedAt);

        try {
            [$acceptance, $wasCreated] = DB::transaction(function () use (
                $customer,
                $order,
                $challenge,
                $acceptanceUuid,
                $acceptedAt,
                $generated,
                $ipAddress,
                $userAgent,
                $sessionIdentifierHash,
                $code,
            ): array {
                $lockedOrder = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
                $existing = ContractAcceptance::query()->whereBelongsTo($lockedOrder, 'serviceOrder')->first();

                if ($existing !== null) {
                    return [$existing, false];
                }

                $lockedChallenge = ContractSigningChallenge::query()->lockForUpdate()->findOrFail($challenge->id);

                if ($lockedChallenge->consumed_at !== null
                    || $lockedChallenge->expires_at->isPast()
                    || $lockedChallenge->attempts >= 5
                    || ! hash_equals($lockedChallenge->session_identifier_hash, $sessionIdentifierHash)
                    || ! Hash::check($code, $lockedChallenge->code_hash)) {
                    throw new ContractSigningException('Sözleşme doğrulaması artık geçerli değil.');
                }

                $acceptance = ContractAcceptance::query()->create([
                    'uuid' => $acceptanceUuid,
                    'contract_version_id' => $lockedOrder->contract_version_id,
                    'service_order_id' => $lockedOrder->id,
                    'customer_id' => $customer->id,
                    'contract_signing_challenge_id' => $lockedChallenge->id,
                    'contract_name_snapshot' => $order->contractVersion->contract->name,
                    'contract_version_snapshot' => $order->contractVersion->version,
                    'signer_name_snapshot' => trim($customer->first_name.' '.$customer->last_name),
                    'company_title_snapshot' => $customer->company_title,
                    'email_snapshot' => $customer->email,
                    'phone_snapshot' => $customer->phone,
                    'acceptance_method' => ContractAcceptanceMethod::EmailOtp,
                    'delivery_channel' => $lockedChallenge->delivery_channel,
                    'source_document_hash' => $lockedChallenge->source_document_hash,
                    'signature_hash' => $lockedChallenge->signature_hash,
                    'signed_document_hash' => $generated['signed_document_hash'],
                    'signature_path' => $lockedChallenge->signature_path,
                    'document_path' => $generated['document_path'],
                    'accepted_at' => $acceptedAt,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'session_identifier_hash' => $sessionIdentifierHash,
                ]);

                $lockedChallenge->update([
                    'verified_at' => now(),
                    'consumed_at' => now(),
                ]);
                $lockedOrder->update([
                    'status' => ServiceOrderStatus::ContractAccepted,
                    'last_error' => null,
                ]);

                return [$acceptance, true];
            });
        } catch (Throwable $exception) {
            $this->documents->delete($generated['document_path']);

            throw $exception;
        }

        if (! $wasCreated) {
            $this->documents->delete($generated['document_path']);

            return $acceptance;
        }

        $this->audit->record($order, 'otp_verified', [
            'challenge_uuid' => $challenge->uuid,
            'delivery_channel' => $challenge->delivery_channel,
        ]);

        $this->audit->record($order, 'contract_accepted', [
            'acceptance_uuid' => $acceptance->uuid,
            'source_document_hash' => $acceptance->source_document_hash,
            'signed_document_hash' => $acceptance->signed_document_hash,
            'acceptance_method' => $acceptance->acceptance_method->value,
        ], $acceptance);
        $this->createInvoiceIfNeeded($order, $acceptance);

        return $acceptance->refresh();
    }

    private function verifyChallenge(
        ContractSigningChallenge $challenge,
        string $code,
        string $sessionIdentifierHash,
    ): void {
        $result = DB::transaction(function () use ($challenge, $code, $sessionIdentifierHash): array {
            $locked = ContractSigningChallenge::query()->lockForUpdate()->findOrFail($challenge->id);

            if ($locked->consumed_at !== null || $locked->expires_at->isPast() || $locked->attempts >= 5) {
                return ['valid' => false, 'attempts' => $locked->attempts, 'reason' => 'expired'];
            }

            if (! hash_equals($locked->session_identifier_hash, $sessionIdentifierHash)) {
                $locked->update(['consumed_at' => now()]);

                return ['valid' => false, 'attempts' => $locked->attempts, 'reason' => 'session_mismatch'];
            }

            if (! Hash::check($code, $locked->code_hash)) {
                $locked->increment('attempts');
                $locked->refresh();

                if ($locked->attempts >= 5) {
                    $locked->update(['consumed_at' => now()]);
                }

                return ['valid' => false, 'attempts' => $locked->attempts, 'reason' => 'invalid_code'];
            }

            return [
                'valid' => true,
                'attempts' => $locked->attempts,
                'reason' => null,
            ];
        });

        if (! $result['valid']) {
            $this->audit->record($challenge->serviceOrder, 'otp_verification_failed', [
                'challenge_uuid' => $challenge->uuid,
                'attempts' => $result['attempts'],
                'reason' => $result['reason'],
            ]);

            throw new ContractSigningException('Doğrulama kodu geçersiz, süresi dolmuş veya deneme sınırı aşılmış.');
        }

    }

    private function createInvoiceIfNeeded(ServiceOrder $order, ContractAcceptance $acceptance): void
    {
        $existingInvoice = $order->invoice()->first();

        if ($existingInvoice !== null) {
            if ($existingInvoice->status === InvoiceStatus::Unpaid) {
                $order->update(['status' => ServiceOrderStatus::Invoiced, 'last_error' => null]);
            }

            return;
        }

        try {
            $invoice = $this->createInvoice->createForOrder($order->refresh());
            $order->refresh()->update(['status' => ServiceOrderStatus::Invoiced, 'last_error' => null]);
            $this->audit->record($order, 'invoice_created', [
                'acceptance_uuid' => $acceptance->uuid,
                'invoice_uuid' => $invoice->uuid,
                'cari_plus_invoice_id' => $invoice->cari_plus_invoice_id,
            ], $acceptance);
        } catch (CariPlusException $exception) {
            $order->refresh()->update([
                'status' => ServiceOrderStatus::InvoiceFailed,
                'last_error' => $exception->getMessage(),
            ]);
            $this->audit->record($order, 'invoice_creation_failed', [
                'acceptance_uuid' => $acceptance->uuid,
                'error' => $exception->getMessage(),
            ], $acceptance);
        }
    }
}
