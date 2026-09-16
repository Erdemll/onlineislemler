<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\OtpVerification;
use App\Support\PhoneNormalizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('customer:create-test
    {email=test@example.com : Test müşterisinin e-posta adresi}
    {phone=05550000001 : Test müşterisinin telefon numarası}
    {--password= : Boş bırakılırsa güvenli bir şifre üretilir}
    {--force : Production ortamında çalıştırmaya izin ver}')]
#[Description('SMS göndermeden kullanılabilecek doğrulanmış bir test müşterisi oluşturur')]
class CreateVerifiedTestCustomer extends Command
{
    public function handle(): int
    {
        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->components->error('Production ortamında kullanmak için --force seçeneği gereklidir.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->argument('email')));
        $phone = PhoneNormalizer::normalize((string) $this->argument('phone'));
        $passwordOption = trim((string) $this->option('password'));
        $password = $passwordOption !== '' ? $passwordOption : Str::password(20);

        $validator = Validator::make([
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'regex:/^\+905[0-9]{9}$/'],
            'password' => ['required', 'string', 'min:12', 'max:128'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $emailOwner = Customer::where('email', $email)->first();
        $phoneOwner = Customer::where('phone', $phone)->first();

        if ($emailOwner && $phoneOwner && ! $emailOwner->is($phoneOwner)) {
            $this->components->error('E-posta ve telefon farklı müşteri kayıtlarına ait.');

            return self::FAILURE;
        }

        $customer = DB::transaction(function () use ($emailOwner, $phoneOwner, $email, $phone, $password): Customer {
            $customer = $emailOwner ?? $phoneOwner ?? new Customer;

            $customer->forceFill([
                'first_name' => 'Test',
                'last_name' => 'Müşteri',
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'is_active' => true,
                'session_version' => $customer->exists
                    ? $customer->session_version + 1
                    : 1,
                'password_changed_at' => now(),
                'remember_token' => null,
            ])->save();

            OtpVerification::where('customer_id', $customer->id)->delete();

            return $customer;
        });

        $this->components->info('Doğrulanmış test müşterisi hazır.');
        $this->table(
            ['Alan', 'Değer'],
            [
                ['E-posta', $customer->email],
                ['Telefon', $customer->phone],
                ['Şifre', $password],
            ],
        );

        return self::SUCCESS;
    }
}
