<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerType;
use App\Models\Customer;
use App\Rules\ValidTurkishIdentificationNumber;
use App\Support\PhoneNormalizer;
use App\Support\TurkeyProvinces;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $accountType = trim((string) $this->input('account_type', CustomerType::Individual->value));
        $companyTitle = $this->input('company_title');

        if (blank($companyTitle)) {
            $companyTitle = $this->input($accountType.'_company_title');
        }

        $this->merge([
            'account_type' => $accountType,
            'first_name' => trim((string) $this->first_name),
            'last_name' => trim((string) $this->last_name),
            'email' => mb_strtolower(trim((string) $this->email)),
            'phone' => PhoneNormalizer::normalize($this->phone),
            'mobile_phone' => PhoneNormalizer::normalize($this->mobile_phone),
            'national_id' => preg_replace('/\D+/', '', (string) $this->national_id),
            'tax_number' => preg_replace('/\D+/', '', (string) $this->tax_number),
            'spending_unit_tax_number' => preg_replace('/\D+/', '', (string) $this->spending_unit_tax_number),
            'company_title' => trim((string) $companyTitle),
            'tax_office' => trim((string) $this->tax_office),
            'district' => trim((string) $this->district),
            'address_line' => trim((string) $this->address_line),
            'spending_unit_title' => trim((string) $this->spending_unit_title),
            'is_public_institution' => $this->boolean('is_public_institution'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'account_type' => ['required', Rule::enum(CustomerType::class)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'national_id' => ['exclude_unless:account_type,individual', 'required', new ValidTurkishIdentificationNumber],
            'tax_number' => ['exclude_unless:account_type,corporate', 'required', 'digits:10'],
            'tax_office' => ['exclude_unless:account_type,corporate', 'required', 'string', 'max:255'],
            'company_title' => [
                Rule::requiredIf(fn (): bool => $this->input('account_type') === CustomerType::Corporate->value),
                'nullable', 'string', 'max:255',
            ],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['required', 'regex:/^\+90[2-5][0-9]{9}$/', 'unique:customers,phone'],
            'mobile_phone' => ['exclude_unless:account_type,corporate', 'nullable', 'regex:/^\+905[0-9]{9}$/'],
            'province_code' => ['required', Rule::in(array_keys(TurkeyProvinces::all()))],
            'district' => ['required', 'string', 'max:100'],
            'address_line' => ['required', 'string', 'max:1000'],
            'is_public_institution' => ['exclude_unless:account_type,corporate', 'boolean'],
            'spending_unit_tax_number' => ['exclude_unless:account_type,corporate', 'nullable', 'digits:10'],
            'spending_unit_title' => ['exclude_unless:account_type,corporate', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('account_type') === CustomerType::Individual->value
                && ! $validator->errors()->has('national_id')
                && Customer::where('national_id_hash', Customer::identityHash((string) $this->national_id))->exists()) {
                $validator->errors()->add('national_id', 'Bu T.C. kimlik numarası zaten kullanılmaktadır.');
            }

            if ($this->input('account_type') === CustomerType::Corporate->value
                && ! $validator->errors()->has('tax_number')
                && Customer::where('tax_number_hash', Customer::identityHash((string) $this->tax_number))->exists()) {
                $validator->errors()->add('tax_number', 'Bu vergi numarası zaten kullanılmaktadır.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'account_type.required' => 'Hesap türünü seçiniz.',
            'first_name.required' => 'Ad alanı zorunludur.',
            'last_name.required' => 'Soyad alanı zorunludur.',
            'tax_number.required' => 'Vergi numarası zorunludur.',
            'tax_number.digits' => 'Vergi numarası 10 haneli olmalıdır.',
            'tax_office.required' => 'Vergi dairesi zorunludur.',
            'company_title.required' => 'Firma ünvanı zorunludur.',
            'email.required' => 'E-posta alanı zorunludur.',
            'email.email' => 'Geçerli bir e-posta adresi giriniz.',
            'email.unique' => 'Bu e-posta adresi zaten kullanılmaktadır.',
            'phone.required' => 'Telefon numarası zorunludur.',
            'phone.regex' => 'Geçerli bir Türkiye telefon numarası giriniz.',
            'phone.unique' => 'Bu telefon numarası zaten kullanılmaktadır.',
            'mobile_phone.regex' => 'Geçerli bir cep telefonu numarası giriniz.',
            'province_code.required' => 'İl seçimi zorunludur.',
            'province_code.in' => 'Geçerli bir il seçiniz.',
            'district.required' => 'İlçe alanı zorunludur.',
            'address_line.required' => 'Açık adres alanı zorunludur.',
            'spending_unit_tax_number.digits' => 'Harcama birimi vergi kimlik numarası 10 haneli olmalıdır.',
            'password.required' => 'Şifre alanı zorunludur.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
        ];
    }
}
