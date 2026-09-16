<?php

namespace App\Http\Requests\Customer;

use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->first_name),
            'last_name' => trim((string) $this->last_name),

            'email' => mb_strtolower(trim($this->email)),

            'phone' => PhoneNormalizer::normalize(
                $this->phone
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:customers,email',
            ],

            'phone' => [
                'required',
                'regex:/^\+90[5][0-9]{9}$/',
                'unique:customers,phone',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Ad alanı zorunludur.',
            'last_name.required' => 'Soyad alanı zorunludur.',

            'email.required' => 'E-posta alanı zorunludur.',
            'email.email' => 'Geçerli bir e-posta adresi giriniz.',
            'email.unique' => 'Bu e-posta adresi zaten kullanılmaktadır.',

            'phone.required' => 'Telefon numarası zorunludur.',
            'phone.regex' => 'Geçerli bir cep telefonu numarası giriniz.',
            'phone.unique' => 'Bu telefon numarası zaten kullanılmaktadır.',

            'password.required' => 'Şifre alanı zorunludur.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
        ];
    }
}
