<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use App\Support\PhoneNormalizer;

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

            'email' => $this->email
                ? mb_strtolower(trim($this->email))
                : null,

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
                'nullable',
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
