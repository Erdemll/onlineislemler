<?php

namespace Tests\Feature\Services\Sms;

use App\Exceptions\SmsDeliveryException;
use App\Services\Sms\VerimorSmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class VerimorSmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.verimor_sms.username' => '908501234567',
            'services.verimor_sms.password' => 'test-api-password',
            'services.verimor_sms.source_address' => 'BASLIGIM',
            'services.verimor_sms.endpoint' => 'https://sms.verimor.com.tr/v2/send.json',
        ]);

        Http::preventStrayRequests();
    }

    public function test_it_sends_otp_using_verimor_json_api(): void
    {
        Http::fake([
            'sms.verimor.com.tr/*' => Http::response('20212'),
        ]);

        app(VerimorSmsService::class)->sendOtp('+905311234567', '123456');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://sms.verimor.com.tr/v2/send.json'
                && $request->method() === 'POST'
                && $request['username'] === '908501234567'
                && $request['password'] === 'test-api-password'
                && $request['source_addr'] === 'BASLIGIM'
                && $request['valid_for'] === '00:05'
                && $request['messages'][0]['dest'] === '905311234567'
                && str_contains($request['messages'][0]['msg'], '123456');
        });
    }

    public function test_source_address_is_omitted_when_not_configured(): void
    {
        config()->set('services.verimor_sms.source_address');

        Http::fake([
            'sms.verimor.com.tr/*' => Http::response('20212'),
        ]);

        app(VerimorSmsService::class)->sendOtp('+905311234567', '123456');

        Http::assertSent(
            fn (Request $request): bool => ! array_key_exists('source_addr', $request->data())
        );
    }

    public function test_http_errors_are_wrapped_as_sms_delivery_exceptions(): void
    {
        Http::fake([
            'sms.verimor.com.tr/*' => Http::response('IP_NOT_ALLOWED', 401),
        ]);

        try {
            app(VerimorSmsService::class)->sendOtp('+905311234567', '123456');
            $this->fail('SMS delivery exception was not thrown.');
        } catch (SmsDeliveryException $exception) {
            $this->assertSame('http', $exception->reason);
            $this->assertSame(401, $exception->status);
            $this->assertInstanceOf(RequestException::class, $exception->getPrevious());
        }
    }

    public function test_connection_errors_are_wrapped_as_sms_delivery_exceptions(): void
    {
        Http::fake([
            'sms.verimor.com.tr/*' => Http::failedConnection(),
        ]);

        $this->expectException(SmsDeliveryException::class);

        app(VerimorSmsService::class)->sendOtp('+905311234567', '123456');
    }

    public function test_unexpected_success_response_is_rejected(): void
    {
        Http::fake([
            'sms.verimor.com.tr/*' => Http::response('unexpected-response'),
        ]);

        $this->expectException(SmsDeliveryException::class);

        app(VerimorSmsService::class)->sendOtp('+905311234567', '123456');
    }

    public function test_missing_credentials_are_rejected_before_request(): void
    {
        config()->set('services.verimor_sms.password');

        Http::fake();

        $this->expectException(SmsDeliveryException::class);

        try {
            app(VerimorSmsService::class)->sendOtp('+905311234567', '123456');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_invalid_destination_is_rejected_before_request(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(VerimorSmsService::class)->sendOtp('+902121234567', '123456');
        } finally {
            Http::assertNothingSent();
        }
    }
}
