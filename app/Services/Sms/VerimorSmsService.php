<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Exceptions\SmsDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class VerimorSmsService implements SmsSender
{
    public function sendOtp(string $phone, string $code): void
    {
        $username = (string) config('services.verimor_sms.username');
        $password = (string) config('services.verimor_sms.password');
        $endpoint = (string) config('services.verimor_sms.endpoint');

        if ($username === '' || $password === '' || $endpoint === '') {
            throw new SmsDeliveryException(reason: 'configuration');
        }

        $destination = ltrim($phone, '+');

        if (! preg_match('/^90[5][0-9]{9}$/', $destination)) {
            throw new InvalidArgumentException('SMS alıcısı geçerli bir Türkiye cep telefonu numarası olmalıdır.');
        }

        $payload = [
            'username' => $username,
            'password' => $password,
            'valid_for' => '00:05',
            'messages' => [
                [
                    'msg' => "Tepenet Güvenlik doğrulama kodunuz: {$code}. Kod 5 dakika geçerlidir.",
                    'dest' => $destination,
                ],
            ],
        ];

        $sourceAddress = config('services.verimor_sms.source_address');

        if (filled($sourceAddress)) {
            $payload['source_addr'] = $sourceAddress;
        }

        try {
            $response = Http::accept('*/*')
                ->asJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->post($endpoint, $payload);
        } catch (ConnectionException $exception) {
            throw new SmsDeliveryException(
                reason: 'connection',
                previous: $exception,
            );
        }

        if ($response->failed()) {
            throw new SmsDeliveryException(
                reason: 'http',
                status: $response->status(),
                previous: $response->toException(),
            );
        }

        if (! ctype_digit(trim($response->body()))) {
            throw new SmsDeliveryException(reason: 'unexpected_response');
        }
    }
}
