<?php

namespace App\Http\Controllers\Payments;

use App\Contracts\ToslaGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\ToslaException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Services\Payments\CompleteToslaPayment;
use App\Services\Tosla\ToslaHash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ToslaCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        ToslaHash $hash,
        ToslaGateway $tosla,
        CompleteToslaPayment $completePayment,
    ): Response {
        if (strlen($request->getContent()) > 16384) {
            return response('', 413);
        }

        $data = $request->all();
        $orderId = is_string($data['OrderId'] ?? null) ? $data['OrderId'] : '';
        $payment = $orderId !== '' && mb_strlen($orderId) <= 20
            ? Payment::query()->where('order_id', $orderId)->first()
            : null;
        $hashValid = $payment !== null && $hash->verifyCallback($data);

        PaymentCallback::create([
            'payment_id' => $payment?->id,
            'order_id' => mb_substr($orderId, 0, 20),
            'transaction_id' => $this->nullableString($data['TransactionId'] ?? null, 20),
            'code' => $this->nullableString($data['Code'] ?? null, 10),
            'message' => $this->nullableString($data['Message'] ?? null),
            'bank_response_code' => $this->nullableString($data['BankResponseCode'] ?? null, 10),
            'bank_response_message' => $this->nullableString($data['BankResponseMessage'] ?? null),
            'request_status' => $this->nullableString($data['RequestStatus'] ?? null, 10),
            'md_status' => $this->nullableString($data['MdStatus'] ?? null, 10),
            'hash_valid' => $hashValid,
            'received_at' => now(),
            'payload' => collect($data)->reject(
                fn (mixed $value, string|int $key): bool => is_string($key) && in_array(mb_strtolower($key), [
                    'cardno', 'cardnumber', 'cvv', 'cvc', 'expiredate',
                ], true)
            )->all(),
        ]);

        if ($payment === null || ! $hashValid) {
            Log::warning('Aköde callback doğrulaması başarısız oldu.', [
                'order_id' => $orderId,
                'hash_valid' => $hashValid,
            ]);

            return $this->redirectPage(false);
        }

        $bankResponseCode = (string) ($data['BankResponseCode'] ?? '');
        $mdStatus = (string) ($data['MdStatus'] ?? '');
        $requestStatus = (int) ($data['RequestStatus'] ?? 0);

        if ($payment->status === PaymentStatus::Paid || $payment->status === PaymentStatus::Refunded) {
            return $this->redirectPage();
        }

        if ($mdStatus !== '1' || $bankResponseCode !== '00' || $requestStatus !== 1) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'response_code' => $bankResponseCode,
            ]);

            return $this->redirectPage();
        }

        try {
            $inquiry = $tosla->inquiry($orderId);
        } catch (ToslaException $exception) {
            report($exception);

            return response('', 503);
        }

        if ($inquiry['request_status'] !== 1) {
            $status = match ($inquiry['request_status']) {
                0 => PaymentStatus::Failed,
                2, 4 => PaymentStatus::Cancelled,
                default => $payment->status,
            };

            Log::warning('Aköde sorgu ödemeyi başarılı doğrulamadı.', [
                'order_id' => $orderId,
                'payment_amount' => $payment->amount_kurus,
                'inquiry_amount' => $inquiry['amount'],
                'inquiry_request_status' => $inquiry['request_status'],
            ]);
            $payment->update([
                'status' => $status,
                'response_code' => $inquiry['bank_response_code'],
            ]);

            return $this->redirectPage();
        }

        $completePayment->handle($payment, $inquiry);

        return $this->redirectPage();
    }

    private function redirectPage(bool $verified = true): Response
    {
        $url = route('customer.invoices.index');
        $message = $verified
            ? 'Ödeme sonucunuz alındı. Fatura sayfanıza yönlendiriliyorsunuz.'
            : 'Ödeme sonucunuz doğrulanamadı. Durumunu kontrol etmek için fatura sayfanıza gidin.';

        return response(
            '<!DOCTYPE html>'
            .'<html lang="tr"><head><meta charset="utf-8">'
            .'<meta http-equiv="refresh" content="0;url='.e($url).'">'
            .'<title>Yönlendiriliyorsunuz</title></head>'
            .'<body><p>'.$message.'</p>'
            .'<a href="'.e($url).'">Devam etmek için tıklayın</a></body></html>',
            $verified ? 200 : 400,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    private function nullableString(mixed $value, int $length = 255): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $length);
    }
}
