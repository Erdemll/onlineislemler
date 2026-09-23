<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

#[Signature('admin:create
    {email : Yönetici e-posta adresi}
    {--name=Tepenet Yönetici : Yönetici adı}')]
#[Description('Giriş yapabilecek bir yönetici hesabı oluşturur veya günceller')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));
        $passwordInput = trim((string) $this->secret('Parola (güvenli parola üretmek için boş bırakın)'));
        $generatedPassword = $passwordInput === '';
        $password = $generatedPassword ? Str::password(24) : $passwordInput;

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => $password,
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'remember_token' => null,
        ])->save();

        $this->components->info('Yönetici hesabı hazır.');
        $this->line("E-posta: {$user->email}");

        if ($generatedPassword) {
            $this->line("Üretilen parola: {$password}");
        }

        return self::SUCCESS;
    }
}
