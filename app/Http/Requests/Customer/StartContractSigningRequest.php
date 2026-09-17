<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartContractSigningRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accepted' => ['accepted'],
            'authority_confirmed' => [
                Rule::requiredIf(fn (): bool => $this->user('customer')?->account_type === CustomerType::Corporate),
                Rule::excludeIf(fn (): bool => $this->user('customer')?->account_type !== CustomerType::Corporate),
                'accepted',
            ],
            'signature_data' => ['required', 'string', 'max:1500000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'accepted.accepted' => 'Sözleşmeyi okuyup kabul ettiğinizi onaylamalısınız.',
            'authority_confirmed.required' => 'Şirket adına imza yetkinizi onaylamalısınız.',
            'authority_confirmed.accepted' => 'Şirket adına imza yetkinizi onaylamalısınız.',
            'signature_data.required' => 'İmzanızı çizmelisiniz.',
            'signature_data.max' => 'İmza verisi izin verilen boyutu aşıyor.',
        ];
    }
}
