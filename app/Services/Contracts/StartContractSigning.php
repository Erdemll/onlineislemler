<?php

namespace App\Services\Contracts;

use App\Enums\ServiceOrderStatus;
use App\Exceptions\ContractSigningException;
use App\Mail\ContractSigningCodeMail;
use App\Models\ContractSigningChallenge;
use App\Models\Customer;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StartContractSigning
{
    public function __construct(
        private RecordContractAuditEvent $audit,
        private EncryptedContractDocumentStorage $documents,
    ) {}

    public function start(
        Customer $customer,
        ServiceOrder $order,
        string $signatureData,
        string $ipAddress,
        ?string $userAgent,
        string $sessionIdentifier,
    ): ContractSigningChallenge {
        $order->loadMissing('contractVersion.contract');
        $this->ensureOrderCanBeSigned($customer, $order);
        try {
            $documentBytes = $this->documents->get($order->contractVersion->source_document_path);
        } catch (RuntimeException $exception) {
            throw new ContractSigningException('Sözleşme dosyasının bütünlüğü doğrulanamadı.', previous: $exception);
        }

        if (! hash_equals($order->contractVersion->source_document_hash, hash('sha256', $documentBytes))) {
            throw new ContractSigningException('Sözleşme dosyasının bütünlüğü doğrulanamadı.');
        }

        $signatureBytes = $this->decodeSignature($signatureData);
        $challengeUuid = (string) Str::uuid();
        $signaturePath = "contracts/signatures/{$order->uuid}/{$challengeUuid}.enc";

        if (! Storage::disk('local')->put($signaturePath, Crypt::encryptString(base64_encode($signatureBytes)))) {
            throw new ContractSigningException('İmza güvenli depolamaya kaydedilemedi.');
        }

        $code = (string) random_int(100000, 999999);
        $challenge = ContractSigningChallenge::query()->create([
            'uuid' => $challengeUuid,
            'service_order_id' => $order->id,
            'customer_id' => $customer->id,
            'delivery_channel' => 'email',
            'delivery_destination' => $customer->email,
            'code_hash' => Hash::make($code),
            'signature_path' => $signaturePath,
            'signature_hash' => hash('sha256', $signatureBytes),
            'source_document_hash' => $order->contractVersion->source_document_hash,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'session_identifier_hash' => hash('sha256', $sessionIdentifier),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => now(),
        ]);

        try {
            Mail::to($customer->email)->send(new ContractSigningCodeMail(
                code: $code,
                contractName: $order->contractVersion->contract->name,
                serviceName: $order->service_name_snapshot,
            ));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($challenge->signature_path);
            $this->audit->record($order, 'otp_delivery_failed', [
                'challenge_uuid' => $challenge->uuid,
                'delivery_channel' => 'email',
            ]);

            throw new ContractSigningException('Sözleşme onay kodu gönderilemedi. Lütfen tekrar deneyin.', previous: $exception);
        }

        try {
            $previousChallenges = DB::transaction(function () use ($customer, $order, $challenge): mixed {
                $lockedOrder = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
                $this->ensureOrderCanBeSigned($customer, $lockedOrder);
                $previous = $lockedOrder->signingChallenges()
                    ->whereKeyNot($challenge->id)
                    ->whereNull('consumed_at')
                    ->get();
                $lockedOrder->signingChallenges()
                    ->whereKeyNot($challenge->id)
                    ->whereNull('consumed_at')
                    ->update(['consumed_at' => now()]);
                $challenge->update(['consumed_at' => null]);
                $lockedOrder->update(['status' => ServiceOrderStatus::OtpPending, 'last_error' => null]);

                return $previous;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($challenge->signature_path);

            throw new ContractSigningException('Sözleşme onay süreci başlatılamadı. Lütfen tekrar deneyin.', previous: $exception);
        }

        Storage::disk('local')->delete($previousChallenges->pluck('signature_path')->all());
        $this->audit->record($order, 'otp_sent', [
            'challenge_uuid' => $challenge->uuid,
            'delivery_channel' => 'email',
            'delivery_destination' => $this->maskEmail($customer->email),
            'source_document_hash' => $challenge->source_document_hash,
            'signature_hash' => $challenge->signature_hash,
        ]);

        return $challenge;
    }

    private function ensureOrderCanBeSigned(Customer $customer, ServiceOrder $order): void
    {
        if ($order->customer_id !== $customer->id) {
            throw new ContractSigningException('Bu hizmet talebine erişemezsiniz.');
        }

        if ($order->acceptance()->exists()) {
            throw new ContractSigningException('Bu sözleşme daha önce kabul edilmiş.');
        }

        if (! in_array($order->status, [ServiceOrderStatus::AwaitingContract, ServiceOrderStatus::OtpPending], true)) {
            throw new ContractSigningException('Bu hizmet talebi sözleşme imzalamaya uygun değil.');
        }
    }

    private function decodeSignature(string $signatureData): string
    {
        if (preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $signatureData, $matches) !== 1) {
            throw new ContractSigningException('İmza yalnızca PNG görüntüsü olarak gönderilebilir.');
        }

        $bytes = base64_decode($matches[1], true);

        if ($bytes === false || $bytes === '' || strlen($bytes) > 1000000) {
            throw new ContractSigningException('İmza verisi geçersiz veya çok büyük.');
        }

        $image = getimagesizefromstring($bytes);

        if ($image === false || ($image['mime'] ?? null) !== 'image/png') {
            throw new ContractSigningException('İmza görüntüsü doğrulanamadı.');
        }

        if ($image[0] < 100 || $image[1] < 50 || $image[0] > 2500 || $image[1] > 1200) {
            throw new ContractSigningException('İmza görüntüsünün boyutları geçersiz.');
        }

        return $bytes;
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2);

        return mb_substr($name, 0, 2).str_repeat('*', max(2, mb_strlen($name) - 2)).'@'.$domain;
    }
}
